<?php

namespace App\Accounting;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class Access
{
    public static function project(User $user, int $id, bool $write = false, bool $manager = false): object
    {
        $p = DB::table('projects')->find($id);
        abort_unless($p, 404);
        $org = DB::table('organization_members')->where('organization_id', $p->organization_id)->where('user_id', $user->id)->first();
        $member = DB::table('project_members')->where('project_id', $id)->where('user_id', $user->id)->first();
        abort_unless($org && $member, 403);
        abort_if($write && ! in_array($member->role, ['manager', 'accountant']), 403);
        abort_if($manager && ($member->role !== 'manager' || $org->role !== 'manager'), 403);

        return $p;
    }
}
