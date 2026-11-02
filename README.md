# حساب ساختمان

نرم‌افزار فارسی مدیریت مالی پروژه‌های ساختمانی؛ Laravel 13، Livewire 4، Tailwind 4 و PHP **8.4.1 یا بالاتر**. توسعه با MySQL 8.4 و SQLite آزمایش می‌شود؛ تولید باید MySQL/InnoDB باشد.

## قابلیت‌های قابل استفاده

- ورود و نقش مدیر، حسابدار و مالک فقط‌خواندنی؛ جداسازی سازمان و پروژه.
- پروژه، هویت مشترک اشخاص، حساب بانک، قرارداد، نسخه سهم مالکان و درصد مدیریت.
- فاکتور چندردیفی با سرفصل سه‌سطحی، مرحله، مقدار اعشاری دقیق، قیمت واحد و تخفیف.
- پیش‌نویس، پیش‌نمایش، تأیید اتمیک، شماره قطعی و دفتر دوطرفه غیرقابل ویرایش.
- حق‌الزحمه روی هزینه مصوب حتی بدون پرداخت؛ تسهیم دقیق هزینه بین مالکان.
- آورده، پرداخت جزئی، پرداخت مستقیم مالک، پرداخت شخصی مجری، تهاتر و تسویه تفکیکی/ترکیبی مجری.
- چک دریافتی و پرداختی، وصول/برگشت/لغو و هشدار سررسید؛ گردش بانک فقط در زمان وصول.
- افتتاحیه متوازن، هزینه انباشته، بدهی‌ها و چک‌های معوق؛ پیش‌نمایش و کنترل تکرار CSV/Excel.
- تعدیل سهم و حق‌الزحمه روی فاکتورها، بدون بازنویسی دفتر؛ سال مالی و بستن دوره.
- داشبورد، گردش حساب، تراز، سهم و آورده مالکان، هزینه سرفصل، وضعیت تسویه، چک، تهاتر و حسابرسی؛ CSV، Excel و PDF فارسی.
- پیوست خصوصی و ثبت وقایع؛ نمایش/ورود تاریخ شمسی و ذخیره میلادی؛ نمایش ریال/تومان.

وضعیت دقیق و محدودیت‌ها در [DEVELOPMENT_STATUS.md](DEVELOPMENT_STATUS.md) است. این نسخه هنوز تأیید آماده‌به‌تولید ندارد.

## محیط توسعه ابری

همین checkout را استفاده کنید؛ هر وظیفه ابری محیط مستقل دارد. worktree جدید نسازید مگر کاربر بخواهد.

```bash
bash scripts/cloud-setup.sh
bash scripts/cloud-start.sh
scripts/dev-tool php artisan test --compact
```

Docker فقط ابزار توسعه است. Node و Composer نیز فقط هنگام build لازم هستند. ابزارها با proxy و TLS معتبر اجرا می‌شوند. اجرای Docker ممکن است به دسترسی خارج از sandbox فرمان نیاز داشته باشد. سرویس از داخل محیط روی پورت 8000 قابل بررسی است؛ لینک پیش‌نمایش عمومی تولید نمی‌شود.

برای مدیر اولیه، PHP بومی:

```bash
php artisan app:manager manager@example.com --name="مدیر" --organization="سازمان"
```

رمز به‌صورت مخفی پرسیده می‌شود. در محیط ابری می‌توانید همین فرمان را در کانتینر PHP تعاملی اجرا کنید. حساب آزمایشی عمومی در تولید ساخته نمی‌شود.

داده نمونه فقط در `APP_ENV=local/testing` و با متغیر خصوصی `DEMO_PASSWORD` حداقل ۱۲ کاراکتر:

```bash
php artisan db:seed
php artisan app:reconcile
```

داده نمونه کاملاً ساختگی است؛ ایمیل آن `demo@example.test` است. Seed در تولید ممنوع شده است. هیچ رمز واقعی یا فایل `.env` در Git نیست.

## توسعه معمول بدون Docker

PHP 8.4.1+ با extensions: bcmath, ctype, curl, dom, fileinfo, filter, gd, hash, intl, mbstring, openssl, pcre, PDO/pdo_mysql, session, tokenizer, xml, xmlreader, xmlwriter, zip. برای تست SQLite، pdo_sqlite نیز لازم است.

```bash
composer install --prefer-dist --no-interaction
cp .env.example .env
php artisan key:generate
npm ci --ignore-scripts
npm run build
php artisan migrate
php artisan test
php artisan serve
```

قبل از migrate اتصال پایگاه داده را در `.env` تنظیم کنید. `migrate:fresh` برای داده واقعی ممنوع است. آزمون‌ها از پایگاه داده آزمایشی مستقل استفاده می‌کنند؛ آزمون MySQL را فقط روی دیتابیس اختصاصی و قابل‌حذف اجرا کنید.

## مستندات

- [راهنمای فارسی](docs/USER_GUIDE.fa.md)
- [قواعد حسابداری و معماری](docs/ACCOUNTING.md)
- [API](docs/API.md)
- [نصب، ارتقا و بازیابی DirectAdmin](DEPLOYMENT.md)
- [نیازمندی اصلی](docs/REQUIREMENTS.md)

بسته نصب با `python3 scripts/build-release.py` ساخته می‌شود؛ استقرار روی هاست واقعی نیازمند تأیید صریح مالک است.
