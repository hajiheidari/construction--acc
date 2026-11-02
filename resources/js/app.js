const $=(s,root=document)=>root.querySelector(s), $$=(s,root=document)=>[...root.querySelectorAll(s)];
const persian=new Intl.NumberFormat('fa-IR');
let unit=localStorage.getItem('accounting-unit')||'rial';
function money(){ $$('[data-money]').forEach(el=>{const n=BigInt(el.dataset.money);el.textContent=unit==='rial'?persian.format(n):`${persian.format(n/10n)}${n%10n? '٫'+persian.format(n<0n?-(n%10n):n%10n):''}`;el.title=unit==='rial'?'ریال':'تومان';});const b=$('#unit-toggle');if(b)b.textContent='نمایش: '+(unit==='rial'?'ریال':'تومان'); }
$('#unit-toggle')?.addEventListener('click',()=>{unit=unit==='rial'?'toman':'rial';localStorage.setItem('accounting-unit',unit);money();});money();
const dateFormat=new Intl.DateTimeFormat('fa-IR-u-ca-persian',{year:'numeric',month:'2-digit',day:'2-digit',timeZone:'Asia/Tehran'});
$$('[data-date]').forEach(el=>{el.textContent=dateFormat.format(new Date(el.dataset.date+'T12:00:00+03:30'));});
const latin=s=>s.replace(/[۰-۹]/g,c=>'۰۱۲۳۴۵۶۷۸۹'.indexOf(c)).replace(/[٠-٩]/g,c=>'٠١٢٣٤٥٦٧٨٩'.indexOf(c));
function dateInputs(){ $$('input[type=date]').forEach(input=>{if(input.dataset.enhanced)return;input.dataset.enhanced='1';const jalali=document.createElement('input');jalali.type='text';jalali.placeholder='تاریخ شمسی: ۱۴۰۵/۰۸/۱۲';jalali.setAttribute('aria-label','تاریخ شمسی');jalali.dir='ltr';jalali.className='jalali-input';input.insertAdjacentElement('afterend',jalali);const sync=()=>{jalali.value=input.value?dateFormat.format(new Date(input.value+'T12:00:00+03:30')):'';};sync();input.addEventListener('change',sync);jalali.addEventListener('change',()=>{const parts=latin(jalali.value).split(/[\/\-]/).map(Number);let found=null;if(parts.length===3 && parts[0]>=1200 && parts[0]<=1600){let dt=new Date(Date.UTC(parts[0]+621,0,1,12));for(let i=0;i<740;i++){const v=dateFormat.formatToParts(dt);const num=t=>Number(latin(v.find(x=>x.type===t)?.value||''));if(num('year')===parts[0] && num('month')===parts[1] && num('day')===parts[2]){found=dt.toISOString().slice(0,10);break;}dt.setUTCDate(dt.getUTCDate()+1);}}jalali.setCustomValidity(found?'':'تاریخ شمسی معتبر وارد کنید.');if(found){input.value=found;input.dispatchEvent(new Event('change',{bubbles:true}));}});});}dateInputs();
$$('form[data-confirm]').forEach(form=>form.addEventListener('submit',e=>{if(!confirm(form.dataset.confirm))e.preventDefault();}));
function addRow(type){const target=$('#'+type+'-rows');if(!target)return;const fragment=$('#'+type+'-template').content.cloneNode(true);target.append(fragment);updateKind();}
$$('[data-add]').forEach(b=>b.addEventListener('click',()=>addRow(b.dataset.add)));
document.addEventListener('click',e=>{const b=e.target.closest('[data-remove]');if(b)b.parentElement.remove();});
function updateKind(){const kind=$('#doc-kind')?.value;if(!kind)return;$$('[data-for]').forEach(el=>{const visible=el.dataset.for.split(',').includes(kind);el.hidden=!visible;$$('input,select,textarea',el).forEach(i=>i.disabled=!visible);});}
$('#doc-kind')?.addEventListener('change',updateKind);
$$('[data-select-kind]').forEach(b=>b.addEventListener('click',()=>{const d=$('#new-document');d.open=true;$('#doc-kind').value=b.dataset.selectKind;updateKind();d.scrollIntoView({behavior:'smooth'});}));
if($('#document-form')){addRow('invoice');addRow('allocation');updateKind();}
const val=id=>$('#'+id)?.value;
function fields(row){return Object.fromEntries($$('[data-field]',row).map(i=>[i.dataset.field,i.value]));}
$('#document-form')?.addEventListener('submit',()=>{const kind=val('doc-kind');let data={};
 if(kind==='invoice')data={party_id:val('doc-party'),items:$$('.invoice-row').map(row=>{const d=fields(row);return {...d,category_id:d.category_id||null,fee_eligible:d.fee_eligible==='1'};})};
 if(['payment','personal_payment','direct_payment','offset','outgoing_check'].includes(kind))data={party_id:val('doc-party'),allocations:$$('.allocation-row').map(fields)};
 if(['incoming_check','contribution'].includes(kind))data={party_id:val('doc-party'),amount:val('doc-amount')};
 if(kind==='direct_payment')data.owner_id=val('doc-owner');
 if(kind==='settlement')data={fee_amount:val('doc-fee'),reimbursement_amount:val('doc-reimbursement')};
 if(kind==='transfer')data={amount:val('doc-amount'),to_bank_id:val('doc-to-bank')};
 if(['contribution','payment','settlement','transfer','incoming_check','outgoing_check'].includes(kind))data.bank_id=val('doc-bank');
 if(['incoming_check','outgoing_check'].includes(kind))Object.assign(data,{number:val('doc-number'),bank_name:val('doc-bank-name'),due_date:val('doc-due'),sayad:val('doc-sayad')||null});
 let key=$('#document-form').dataset.key;if(!key){key=crypto.randomUUID();$('#document-form').dataset.key=key;}
 $('#document-payload').value=JSON.stringify({kind,date:val('doc-date'),reference:val('doc-reference'),description:val('doc-description'),idempotency_key:key,data});
});
let previewData=null;
const ruleForm=$('#rule-form');
if(ruleForm){addRow('owner');ruleForm.addEventListener('input',()=>{$('#rule-approve').hidden=true;previewData=null;});}
function ruleData(){const shares={};$$('.owner-row').forEach(row=>{const d=fields(row);if(shares[d.party_id])throw Error('مالک تکراری است.');shares[d.party_id]=Number(d.bps);});return {contract_id:val('rule-contract'),effective_date:val('rule-date'),posting_date:val('rule-posting-date'),fee_bps:val('rule-fee'),mode:val('rule-mode'),shares};}
async function ruleRequest(approve){const result=$('#rule-result');result.textContent='در حال بررسی…';try{const payload=ruleData();if(approve && JSON.stringify(payload)!==previewData)throw Error('اطلاعات تغییر کرده است؛ دوباره پیش‌نمایش بگیرید.');const response=await fetch(ruleForm.action,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':$('meta[name=csrf-token]').content},body:JSON.stringify({payload:JSON.stringify(payload),approve})});const data=await response.json();if(!response.ok)throw Error(Object.values(data.errors||{}).flat().join(' / ')||data.message||'ثبت انجام نشد.');result.textContent=approve?'قرارداد و تعدیلات ثبت شد.':'پیش‌نمایش: '+data.changes.length+' فاکتور تحت تأثیر است.';data.changes.forEach(c=>{const p=document.createElement('p');p.textContent=`فاکتور ${persian.format(c.invoice_id)}: تغییر حق‌الزحمه ${persian.format(c.fee_difference)} ریال؛ تغییر سهم‌ها: `+Object.entries(c.owner_differences).map(([id,a])=>`شخص ${id}: ${persian.format(a)} ریال`).join('، ');result.append(p);});if(approve){$('#rule-approve').hidden=true;const a=document.createElement('a');a.href=location.href;a.textContent='بازخوانی اطلاعات پروژه ←';a.className='link';result.append(a);}else{previewData=JSON.stringify(payload);$('#rule-approve').hidden=false;}}catch(e){result.textContent=e.message;result.className='wide notice error';}}
ruleForm?.addEventListener('submit',e=>{e.preventDefault();ruleRequest(false);});$('#rule-approve')?.addEventListener('click',()=>{if(confirm('ثبت قطعی قرارداد و تعدیلات مالی انجام شود؟'))ruleRequest(true);});
