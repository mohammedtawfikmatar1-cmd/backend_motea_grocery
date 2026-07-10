# دليل اختبار دورة حياة Auth API V1 عبر Postman

هذا المستند يشرح طريقة اختبار نظام المصادقة والتحقق عبر Postman من لحظة إرسال الطلب حتى وصوله إلى قاعدة البيانات وعودة الـ response.

الهدف هنا ليس شرح الكود سطراً بسطر، بل توضيح مسار التنفيذ:

```text
Postman Request
    -> Route
    -> FormRequest
    -> AuthController method
    -> AuthService method
    -> VerificationCodeService / VerificationNotificationService
    -> Database
    -> ApiResponse
```

## 1. المتطلبات قبل الاختبار

شغل XAMPP وتأكد أن MySQL يعمل.

نفذ migrations:

```bash
php artisan migrate
```

يفضل أثناء الاختبار المحلي أن يكون البريد على log mailer حتى ترى كود OTP بدون إعداد SMTP:

```env
MAIL_MAILER=log
QUEUE_CONNECTION=sync
```

بعد تعديل `.env` نفذ:

```bash
php artisan config:clear
```

شغل السيرفر:

```bash
php artisan serve
```

Base URL المقترح في Postman:

```text
http://127.0.0.1:8000/api/v1
```

إذا كنت تستخدم Apache داخل XAMPP مباشرة فقد يكون المسار:

```text
http://localhost/motea_grocery/public/api/v1
```

## 2. متغيرات Postman Environment

أنشئ Environment باسم:

```text
Motea Grocery Local
```

وأضف المتغيرات التالية:

```text
base_url = http://127.0.0.1:8000/api/v1
email = postman_user_001@example.com
phone = 0500000001
password = Password12345
new_password = NewPassword12345
token =
email_otp =
reset_otp =
```

استخدم `{{base_url}}` في كل الطلبات.

## 3. تهيئة بيانات الاختبار اختيارياً

إذا أردت حذف مستخدم الاختبار قبل البدء، نفذ هذه الاستعلامات بحذر في قاعدة البيانات:

```sql
SET @test_email = 'postman_user_001@example.com';

DELETE FROM personal_access_tokens
WHERE tokenable_type = 'App\\Models\\User'
AND tokenable_id IN (
    SELECT id FROM users WHERE email = @test_email
);

DELETE FROM verification_codes
WHERE user_id IN (
    SELECT id FROM users WHERE email = @test_email
);

DELETE FROM users
WHERE email = @test_email;
```

## 4. Route Map

المسارات الحالية:

```text
POST /api/v1/auth/register
POST /api/v1/auth/login
POST /api/v1/auth/forgot-password
POST /api/v1/auth/reset-password
POST /api/v1/auth/verify-email
POST /api/v1/auth/verification-code/resend
POST /api/v1/auth/logout
```

المسار الوحيد المحمي حالياً:

```text
POST /api/v1/auth/logout
```

ويحتاج:

```text
Authorization: Bearer {{token}}
```

## 5. الاختبار الأول: Register

### Postman Request

Method:

```text
POST
```

URL:

```text
{{base_url}}/auth/register
```

Headers:

```text
Accept: application/json
Content-Type: application/json
Accept-Language: ar
```

Body:

```json
{
    "name": "Postman User",
    "email": "{{email}}",
    "phone": "{{phone}}",
    "birth_date": "1995-01-01",
    "password": "{{password}}",
    "password_confirmation": "{{password}}"
}
```

### سلسلة الاستدعاء

```text
Postman
-> POST /api/v1/auth/register
-> RegisterUserRequest
-> AuthController::register()
-> AuthService::register()
-> User::query()->create()
-> VerificationCodeService::create()
-> VerificationCodeService::hasValidCode()
-> VerificationCodeService::invalidate()
-> VerificationCodeService::generate()
-> VerificationCode::query()->create()
-> VerificationNotificationService::send()
-> VerificationNotificationService::sendEmail()
-> User::notify(VerificationCodeNotification)
-> ApiResponse::created()
```

### ماذا يحدث في قاعدة البيانات

جدول `users`:

```text
يتم إنشاء مستخدم جديد.
email_verified_at = null
```

جدول `verification_codes`:

```text
user_id = id المستخدم الجديد
type = email
code = كود مكون من 5 أرقام
attempts = 0
expires_at = الآن + 10 دقائق
verified_at = null
```

### Response متوقع

Status:

```text
201 Created
```

شكل عام:

```json
{
    "success": true,
    "message": "تم إنشاء الحساب بنجاح.",
    "data": {
        "user": {},
        "verification": {
            "type": "email",
            "expires_at": "..."
        }
    }
}
```

### استخراج كود التحقق من قاعدة البيانات

للاختبار فقط:

```sql
SELECT vc.code, vc.type, vc.attempts, vc.expires_at, vc.verified_at
FROM verification_codes vc
JOIN users u ON u.id = vc.user_id
WHERE u.email = 'postman_user_001@example.com'
AND vc.type = 'email'
ORDER BY vc.created_at DESC
LIMIT 1;
```

ضع القيمة داخل Postman variable:

```text
email_otp = الكود من قاعدة البيانات
```

## 6. الاختبار الثاني: Resend Verification Code قبل انتهاء الكود

هذا اختبار مقصود للتأكد أن النظام يرفض إعادة إرسال الكود إذا يوجد كود صالح.

### Postman Request

URL:

```text
{{base_url}}/auth/verification-code/resend
```

Body:

```json
{
    "email": "{{email}}"
}
```

### سلسلة الاستدعاء

```text
Postman
-> POST /api/v1/auth/verification-code/resend
-> ResendVerificationCodeRequest
-> AuthController::resendVerificationCode()
-> AuthService::resendVerificationCode()
-> VerificationCodeService::create()
-> VerificationCodeService::hasValidCode()
-> ValidationException
```

### ماذا يحدث في قاعدة البيانات

```text
لا يتم إنشاء كود جديد.
لا يتم حذف الكود الحالي.
يبقى نفس السجل في verification_codes.
```

### Response متوقع

Status:

```text
422 Unprocessable Entity
```

رسالة متوقعة:

```text
يوجد رمز تحقق صالح تم إرساله مسبقاً. يرجى الانتظار حتى انتهاء صلاحيته.
```

## 7. الاختبار الثالث: Verify Email

### Postman Request

URL:

```text
{{base_url}}/auth/verify-email
```

Body:

```json
{
    "email": "{{email}}",
    "code": "{{email_otp}}"
}
```

### سلسلة الاستدعاء

```text
Postman
-> POST /api/v1/auth/verify-email
-> VerifyEmailRequest
-> AuthController::verifyEmail()
-> AuthService::verifyEmail()
-> VerificationCodeService::verify()
-> VerificationCodeService::markAsVerified()
-> User::forceFill(['email_verified_at' => now()])
-> ApiResponse::success()
```

### ماذا يحدث في قاعدة البيانات

جدول `verification_codes`:

```text
verified_at يتم تعبئته.
attempts لا يزيد إذا كان الكود صحيحاً.
```

جدول `users`:

```text
email_verified_at يتم تعبئته.
```

### SQL للتحقق

```sql
SELECT email, email_verified_at
FROM users
WHERE email = 'postman_user_001@example.com';

SELECT type, attempts, expires_at, verified_at
FROM verification_codes
WHERE user_id = (
    SELECT id FROM users WHERE email = 'postman_user_001@example.com'
)
AND type = 'email'
ORDER BY created_at DESC
LIMIT 1;
```

### Response متوقع

Status:

```text
200 OK
```

شكل عام:

```json
{
    "success": true,
    "message": "تم التحقق بنجاح.",
    "data": {
        "user": {}
    }
}
```

## 8. الاختبار الرابع: Login

### Postman Request

URL:

```text
{{base_url}}/auth/login
```

Body:

```json
{
    "email": "{{email}}",
    "password": "{{password}}"
}
```

### سلسلة الاستدعاء

```text
Postman
-> POST /api/v1/auth/login
-> LoginUserRequest
-> AuthController::login()
-> AuthService::login()
-> AuthService::findUserByEmail()
-> Hasher::check()
-> AuthService::createAccessToken()
-> User::createToken()
-> personal_access_tokens insert
-> ApiResponse::success()
```

### ماذا يحدث في قاعدة البيانات

جدول `personal_access_tokens`:

```text
يتم إنشاء token جديد.
token يتم تخزينه كـ hash وليس النص الأصلي.
tokenable_type = App\Models\User
tokenable_id = id المستخدم
```

### Response متوقع

Status:

```text
200 OK
```

شكل عام:

```json
{
    "success": true,
    "message": "تم تسجيل الدخول بنجاح.",
    "data": {
        "user": {},
        "access_token": "...",
        "token_type": "Bearer"
    }
}
```

### Postman Tests لحفظ التوكن

ضع هذا في تبويب Tests داخل طلب Login:

```javascript
const json = pm.response.json();

if (json.data && json.data.access_token) {
    pm.environment.set('token', json.data.access_token);
}
```

### SQL للتحقق

```sql
SELECT pat.id, pat.tokenable_type, pat.tokenable_id, pat.name, pat.last_used_at, pat.created_at
FROM personal_access_tokens pat
JOIN users u ON u.id = pat.tokenable_id
WHERE u.email = 'postman_user_001@example.com'
ORDER BY pat.created_at DESC;
```

## 9. الاختبار الخامس: Logout

### Postman Request

URL:

```text
{{base_url}}/auth/logout
```

Authorization:

```text
Bearer Token = {{token}}
```

Headers:

```text
Accept: application/json
```

Body:

```text
لا يوجد body.
```

### سلسلة الاستدعاء

```text
Postman
-> POST /api/v1/auth/logout
-> auth:sanctum middleware
-> LogoutRequest
-> AuthController::logout()
-> AuthService::logout()
-> User::currentAccessToken()
-> current token delete()
-> ApiResponse::success()
```

### ماذا يحدث في قاعدة البيانات

جدول `personal_access_tokens`:

```text
يتم حذف التوكن المستخدم في الطلب الحالي.
```

### Response متوقع

Status:

```text
200 OK
```

شكل عام:

```json
{
    "success": true,
    "message": "تم تسجيل الخروج بنجاح.",
    "data": null
}
```

### اختبار سلبي بعد Logout

أرسل Logout مرة ثانية بنفس التوكن.

المتوقع:

```text
401 Unauthenticated
```

## 10. الاختبار السادس: Forgot Password

### Postman Request

URL:

```text
{{base_url}}/auth/forgot-password
```

Body:

```json
{
    "email": "{{email}}"
}
```

### سلسلة الاستدعاء

```text
Postman
-> POST /api/v1/auth/forgot-password
-> ForgotPasswordRequest
-> AuthController::forgotPassword()
-> AuthService::forgotPassword()
-> AuthService::findUserByEmail()
-> VerificationCodeService::create(type: PASSWORD_RESET)
-> VerificationCodeService::hasValidCode()
-> VerificationCodeService::invalidate()
-> VerificationCodeService::generate()
-> VerificationCode::query()->create()
-> VerificationNotificationService::send()
-> VerificationNotificationService::sendEmail()
-> User::notify(VerificationCodeNotification)
-> ApiResponse::success()
```

### ماذا يحدث في قاعدة البيانات

جدول `verification_codes`:

```text
type = password_reset
attempts = 0
expires_at = الآن + 10 دقائق
verified_at = null
```

### استخراج كود إعادة التعيين

```sql
SELECT vc.code, vc.type, vc.attempts, vc.expires_at, vc.verified_at
FROM verification_codes vc
JOIN users u ON u.id = vc.user_id
WHERE u.email = 'postman_user_001@example.com'
AND vc.type = 'password_reset'
ORDER BY vc.created_at DESC
LIMIT 1;
```

ضع القيمة في:

```text
reset_otp
```

### Response متوقع

Status:

```text
200 OK
```

شكل عام:

```json
{
    "success": true,
    "message": "تم إرسال رمز التحقق.",
    "data": {
        "sent": true
    }
}
```

## 11. الاختبار السابع: Reset Password

### Postman Request

URL:

```text
{{base_url}}/auth/reset-password
```

Body:

```json
{
    "email": "{{email}}",
    "code": "{{reset_otp}}",
    "password": "{{new_password}}",
    "password_confirmation": "{{new_password}}"
}
```

### سلسلة الاستدعاء

```text
Postman
-> POST /api/v1/auth/reset-password
-> ResetPasswordRequest
-> AuthController::resetPassword()
-> AuthService::resetPassword()
-> AuthService::findUserOrFail()
-> VerificationCodeService::verify(type: PASSWORD_RESET)
-> VerificationCodeService::markAsVerified()
-> User::forceFill(['password' => hashed password])
-> User::tokens()->delete()
-> ApiResponse::success()
```

### ماذا يحدث في قاعدة البيانات

جدول `verification_codes`:

```text
سجل password_reset يتم تعبئة verified_at له.
```

جدول `users`:

```text
password يتغير إلى hash جديد.
```

جدول `personal_access_tokens`:

```text
كل tokens الخاصة بالمستخدم يتم حذفها.
```

### SQL للتحقق

```sql
SELECT password, updated_at
FROM users
WHERE email = 'postman_user_001@example.com';

SELECT type, attempts, verified_at
FROM verification_codes
WHERE user_id = (
    SELECT id FROM users WHERE email = 'postman_user_001@example.com'
)
AND type = 'password_reset'
ORDER BY created_at DESC
LIMIT 1;

SELECT *
FROM personal_access_tokens
WHERE tokenable_type = 'App\\Models\\User'
AND tokenable_id = (
    SELECT id FROM users WHERE email = 'postman_user_001@example.com'
);
```

### Response متوقع

Status:

```text
200 OK
```

شكل عام:

```json
{
    "success": true,
    "message": "تمت إعادة تعيين كلمة المرور بنجاح.",
    "data": null
}
```

## 12. الاختبار الثامن: Login بكلمة المرور الجديدة

### Postman Request

URL:

```text
{{base_url}}/auth/login
```

Body:

```json
{
    "email": "{{email}}",
    "password": "{{new_password}}"
}
```

### سلسلة الاستدعاء

```text
Postman
-> LoginUserRequest
-> AuthController::login()
-> AuthService::login()
-> Hasher::check()
-> User::createToken()
-> ApiResponse::success()
```

### المتوقع

```text
200 OK
يتم إصدار token جديد.
```

احفظ التوكن الجديد بنفس Tests script الخاص بطلب Login.

## 13. اختبارات سلبية مهمة

### 13.1 كود تحقق خاطئ

أرسل:

```json
{
    "email": "{{email}}",
    "code": "00000"
}
```

على:

```text
POST /auth/verify-email
```

سلسلة الاستدعاء:

```text
AuthController::verifyEmail()
-> AuthService::verifyEmail()
-> VerificationCodeService::verify()
-> VerificationCodeService::incrementAttempts()
-> ValidationException
```

قاعدة البيانات:

```text
attempts يزيد بمقدار 1.
verified_at يبقى null.
email_verified_at يبقى null إذا لم يكن مفعلاً.
```

### 13.2 تجاوز عدد المحاولات

كرر إدخال كود خاطئ حتى يصل `attempts` إلى 5.

المتوقع:

```text
422
تم تجاوز الحد الأقصى لمحاولات التحقق.
```

### 13.3 كود منتهي الصلاحية

للاختبار فقط، اجعل الكود منتهي الصلاحية:

```sql
UPDATE verification_codes vc
JOIN users u ON u.id = vc.user_id
SET vc.expires_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE)
WHERE u.email = 'postman_user_001@example.com'
AND vc.type = 'email'
AND vc.verified_at IS NULL;
```

ثم حاول استخدامه.

المتوقع:

```text
422
انتهت صلاحية رمز التحقق.
```

بعدها يمكنك طلب resend لأن الكود القديم انتهى.

### 13.4 Login بكلمة مرور خاطئة

```json
{
    "email": "{{email}}",
    "password": "wrong-password"
}
```

سلسلة الاستدعاء:

```text
AuthController::login()
-> AuthService::login()
-> Hasher::check()
-> AuthenticationException
```

المتوقع:

```text
401
```

### 13.5 Logout بدون Token

أرسل:

```text
POST /api/v1/auth/logout
```

بدون Authorization header.

المتوقع:

```text
401 Unauthenticated
```

## 14. دورة الحياة الكاملة المختصرة

```text
1. Register
   Postman -> AuthController::register -> AuthService::register
   -> User insert
   -> VerificationCodeService::create
   -> verification_codes insert
   -> Notification
   -> 201 response

2. Verify Email
   Postman -> AuthController::verifyEmail -> AuthService::verifyEmail
   -> VerificationCodeService::verify
   -> verification_codes.verified_at update
   -> users.email_verified_at update
   -> 200 response

3. Login
   Postman -> AuthController::login -> AuthService::login
   -> User::createToken
   -> personal_access_tokens insert
   -> token response

4. Logout
   Postman -> auth:sanctum
   -> AuthController::logout -> AuthService::logout
   -> personal_access_tokens delete
   -> 200 response

5. Forgot Password
   Postman -> AuthController::forgotPassword -> AuthService::forgotPassword
   -> VerificationCodeService::create(PASSWORD_RESET)
   -> verification_codes insert
   -> Notification
   -> 200 response

6. Reset Password
   Postman -> AuthController::resetPassword -> AuthService::resetPassword
   -> VerificationCodeService::verify(PASSWORD_RESET)
   -> users.password update
   -> personal_access_tokens delete
   -> 200 response
```

## 15. جدول تتبع سريع

| العملية | Controller Method | Service Method | جدول قاعدة البيانات | Response |
|---|---|---|---|---|
| Register | `register()` | `AuthService::register()` | `users`, `verification_codes` | 201 |
| Resend | `resendVerificationCode()` | `AuthService::resendVerificationCode()` | `verification_codes` | 200 أو 422 |
| Verify Email | `verifyEmail()` | `AuthService::verifyEmail()` | `verification_codes`, `users` | 200 |
| Login | `login()` | `AuthService::login()` | `personal_access_tokens` | 200 |
| Logout | `logout()` | `AuthService::logout()` | `personal_access_tokens` | 200 |
| Forgot Password | `forgotPassword()` | `AuthService::forgotPassword()` | `verification_codes` | 200 |
| Reset Password | `resetPassword()` | `AuthService::resetPassword()` | `verification_codes`, `users`, `personal_access_tokens` | 200 |

## 16. ملاحظات Postman مهمة

استخدم دائماً:

```text
Accept: application/json
```

حتى يرجع Laravel أخطاء JSON بدلاً من HTML.

استخدم:

```text
Accept-Language: ar
```

أو:

```text
Accept-Language: en
```

لاختبار الترجمة.

بعد Login، احفظ token في Environment variable.

بعد Logout أو Reset Password، اعتبر التوكن القديم غير صالح.

عند اختبار OTP لا تعتمد على البريد الحقيقي في local. استخدم قاعدة البيانات أو `storage/logs/laravel.log` إذا كان `MAIL_MAILER=log`.

## 17. ترتيب Collection المقترح في Postman

أنشئ Collection باسم:

```text
Motea Grocery Auth API V1
```

ورتب الطلبات بهذا الشكل:

```text
01 Register
02 Resend Verification Code - Should Fail Before Expiry
03 Verify Email
04 Login
05 Logout
06 Forgot Password
07 Reset Password
08 Login With New Password
09 Negative - Wrong OTP
10 Negative - Logout Without Token
```

هذا الترتيب يختبر دورة حياة المستخدم من البداية إلى النهاية.
