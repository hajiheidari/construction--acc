@extends('accounting.layout')
@section('content')
<h1>پیش‌نمایش افتتاحیه</h1><p class="muted">فایل هنوز به دفتر مالی منتقل نشده است. هر خطا را در فایل اصلاح کنید و دوباره بارگذاری کنید.</p>
@if($importErrors)<div class="notice error">@foreach($importErrors as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<div class="card table-wrap"><table><thead><tr><th>حساب</th><th>بدهکار ریال</th><th>بستانکار ریال</th><th>شخص</th><th>بانک</th></tr></thead><tbody>@foreach($rows as $row)<tr><td>{{ $row['account'] }}</td><td>{{ $row['debit'] }}</td><td>{{ $row['credit'] }}</td><td>{{ $row['party_id'] }}</td><td>{{ $row['bank_id'] }}</td></tr>@endforeach</tbody></table></div>
@if(!$importErrors)<form method="post" action="/projects/{{ $project }}/imports/{{ $id }}/approve" class="card stack">@csrf<label class="checklist"><input type="checkbox" name="reconciled" value="1" required> مانده بانک، بدهی‌ها، آورده‌ها، هزینه‌های قبلی و مطالبات مجری را با اسناد مبنا تطبیق دادم؛ این سوابق دوباره وارد نخواهند شد.</label><button>ایجاد پیش‌نویس افتتاحیه برای تأیید نهایی</button></form>@endif<a href="/projects/{{ $project }}" class="link">بازگشت به پروژه ←</a>
@endsection
