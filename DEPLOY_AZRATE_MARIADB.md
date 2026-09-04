# رفع azrate.online مؤقتًا على MariaDB

## الملفات المطلوبة

ارفع مجلد المشروع كاملًا، وتأكد خصوصًا من وجود:

- `.env`
- `vendor/` إذا لن تشغّل Composer على السيرفر
- `public/build/`
- `storage/app/public/` بما يحتويه من الصور
- `database/backups/azra_alam_mariadb_data.sql`
- `database/data/kuwait_governorates_areas.json` (مدن ومناطق الكويت — يقرأه seeder المناطق)

يجب أن يكون Document Root للدومين موجّهًا إلى مجلد `public` داخل المشروع، وليس جذر المشروع.

## متطلبات السيرفر

- PHP 8.3 أو أحدث.
- إضافات PHP المعتادة للارافيل، وأهمها `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`, `tokenizer`, `xml`, `ctype`, `json` و`gd` أو `imagick` للصور.
- MariaDB تعمل على `127.0.0.1:3306`، أو تعديل `DB_HOST` و`DB_PORT` داخل `.env` حسب إعدادات مزود السيرفر.
- صلاحية الكتابة لمجلدي `storage` و`bootstrap/cache` بواسطة مستخدم خادم الويب.
- المنطقة الزمنية للتطبيق أصبحت `Asia/Kuwait` افتراضيًا (`APP_TIMEZONE` في `.env` لتغييرها) — مواعيد المعاينات وتذكيراتها تُحسب بها.

## خطوات قاعدة البيانات

> **تنبيه مهم:** ملف النسخة `azra_alam_mariadb_data.sql` الحالي مُصدَّر قبل تعديلات شاشة العملاء
> (أعمدة `area_id` و`in_person` و`visit_times` و`property_address` و`desired_unit_type_id` حُذفت من جدول `clients`).
> أعد تصدير النسخة من Oracle **بعد** تشغيل الـ migrations الجديدة محليًا (`php scripts/export_oracle_data_for_mariadb.php`)
> وإلا سيفشل استيراد جدول `clients`.

نفّذ من داخل مجلد المشروع على السيرفر:

```bash
php artisan migrate --force
```

ثم استورد بيانات Oracle المحوّلة إلى MariaDB:

```bash
mariadb -h 127.0.0.1 -u azra_alam -p azra_alam < database/backups/azra_alam_mariadb_data.sql
```

سيطلب MariaDB كلمة المرور بشكل آمن. إذا كان الأمر المتاح على السيرفر اسمه `mysql` بدل `mariadb`:

```bash
mysql -h 127.0.0.1 -u azra_alam -p azra_alam < database/backups/azra_alam_mariadb_data.sql
```

يمكن أيضًا استيراد الملف من phpMyAdmin بعد تشغيل migrations، لأن ملف النسخة يحتوي البيانات فقط والجداول ينشئها Laravel.

**بعد الاستيراد** شغّل seeder المدن والمناطق (آمن للتكرار: يربط المناطق الموجودة بمدنها ويضيف الناقص فقط ويستكمل المدينة في احتياجات العملاء):

```bash
php artisan db:seed --class=KuwaitAreasSeeder --force
```

## إكمال التجهيز

```bash
php artisan permission:cache-reset
php artisan storage:link
php artisan optimize
```

افحص الاتصال وعدد السجلات:

```bash
php artisan db:show --counts
```

## تذكيرات المعاينات

- التذكير يُفحص تلقائيًا كل دقيقة من متصفح أي مستخدم فاتح لوحة التحكم (`/dashboard/notifications/poll`) — لا يحتاج cron.
- اختياريًا لتشغيل التذكيرات حتى بدون متصفح مفتوح، أضف مهمة cron كل دقيقة:

```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

## البحث في ملاحظات العملاء

على MariaDB يُنشئ migration فهرس `FULLTEXT` على `clients.notes` ويُستخدم تلقائيًا (مطابقة بداية الكلمة، الحد الأدنى 3 أحرف).
لو أردت الرجوع إلى البحث الجزئي `LIKE` أضف في `.env`:

```
CLIENTS_NOTES_FULLTEXT=false
```

## Composer بدون Oracle مؤقتًا

حزمة Oracle بقيت في المشروع حتى يمكن الرجوع إليها لاحقًا. إذا احتجت تشغيل Composer على سيرفر لا يحتوي `oci8` فاستخدم:

```bash
composer install --no-dev --optimize-autoloader --ignore-platform-req=ext-oci8
```

لا تحتاج هذا الأمر إذا رفعت مجلد `vendor` الحالي كاملًا وكان إصدار PHP على السيرفر متوافقًا.

## التبديل بين Oracle وMariaDB في `.env`

الكتلتان محفوظتان داخل `.env`، واحدة فعّالة والأخرى معلّقة. مهم: كتلة Oracle يجب أن تستخدم أسماء `DB_*` وليس `ORACLE_DB_*`، لأن `config/database.php` يقرأ `DB_HOST` و`DB_SERVICE_NAME` وغيرها مباشرة.

قيم Oracle للتطوير المحلي:

```
DB_CONNECTION=oracle
DB_HOST=127.0.0.1
DB_PORT=1521
DB_SERVICE_NAME=XEPDB1
DB_DATABASE=XEPDB1
DB_USERNAME=ALAM
DB_PASSWORD=123456789
DB_CHARSET=AL32UTF8
```

بعد أي تبديل نفّذ:

```bash
php artisan config:clear
php artisan migrate
php artisan permission:cache-reset
```

قاعدتا البيانات منفصلتان، لذلك أي migration جديدة تُنفَّذ على كل جهة عند التبديل إليها.
