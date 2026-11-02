<?php
namespace App\Accounting;
use Illuminate\Support\Facades\DB;
final class Reports {
 public function summary(int $p,?string $from=null,?string $to=null): array {
  $query=DB::table('journal_lines as l')->join('journal_entries as j','j.id','=','l.journal_entry_id')->where('l.project_id',$p)->where('j.status','posted');
  if($from)$query->where('j.date','>=',$from);if($to)$query->where('j.date','<=',$to);
  $trial=(clone $query)->selectRaw('l.account,SUM(l.debit) as debit,SUM(l.credit) as credit')->groupBy('l.account')->get();$balances=[];foreach($trial as $row)$balances[$row->account]=(int)$row->debit-(int)$row->credit;
  $allocationQ=DB::table('owner_cost_allocations as a')->join('journal_entries as j','j.id','=','a.journal_entry_id')->where('a.project_id',$p);if($from)$allocationQ->where('j.date','>=',$from);if($to)$allocationQ->where('j.date','<=',$to);
  $owners=$allocationQ->selectRaw('a.party_id,SUM(a.amount) as allocated')->groupBy('a.party_id')->pluck('allocated','party_id');
  $contributions=(clone $query)->where('l.account','contribution')->selectRaw('l.party_id,SUM(l.credit-l.debit) as contributed')->groupBy('l.party_id')->pluck('contributed','party_id');
  $names=DB::table('parties')->pluck('name','id');$ownerRows=[];foreach(array_unique([...$owners->keys()->all(),...$contributions->keys()->all()]) as $owner){$share=(int)($owners[$owner]??0);$contribution=(int)($contributions[$owner]??0);$ownerRows[]=['party_id'=>$owner,'name'=>$names[$owner]??'','allocated'=>$share,'contributed'=>$contribution,'difference'=>$share-$contribution];}
  $bankRows=(clone $query)->where('l.account','bank')->selectRaw('l.bank_account_id,SUM(l.debit-l.credit) as balance')->groupBy('l.bank_account_id')->get();$bankNames=DB::table('bank_accounts')->where('project_id',$p)->pluck('name','id');foreach($bankRows as $b)$b->name=$bankNames[$b->bank_account_id]??'';
  $project=DB::table('projects')->find($p);$cost=($balances['cost']??0)+($balances['fee_cost']??0);
  $journals=DB::table('journal_lines')->where('project_id',$p)->selectRaw('journal_entry_id,SUM(debit-credit) as difference')->groupBy('journal_entry_id')->havingRaw('SUM(debit-credit) <> 0')->count();
  return ['project'=>$project,'trial'=>$trial,'balances'=>$balances,'cost'=>$cost,'budget_remaining'=>$project->budget-$cost,'owners'=>$ownerRows,'banks'=>$bankRows,'reconciliation'=>['unbalanced_journals'=>$journals,'trial_difference'=>array_sum($balances),'allocated_cost'=>(int)$owners->sum(),'total_cost'=>$cost,'allocation_difference'=>$cost-(int)$owners->sum()],'pending_approvals'=>DB::table('documents')->where('project_id',$p)->where('status','draft')->count()];
 }
 public function ledger(int $p,array $filters=[]): object {
  $q=DB::table('journal_lines as l')->join('journal_entries as j','j.id','=','l.journal_entry_id')->where('l.project_id',$p)->where('j.status','posted');
  foreach(['account','party_id','bank_account_id'] as $key)if(!empty($filters[$key]))$q->where('l.'.$key,$filters[$key]);
  if(!empty($filters['from']))$q->where('j.date','>=',$filters['from']);if(!empty($filters['to']))$q->where('j.date','<=',$filters['to']);
  return $q->select('l.*','j.date','j.number','j.kind','j.description','j.document_id')->orderBy('j.date')->orderBy('j.id')->orderBy('l.id')->get();
 }
}
