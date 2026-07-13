<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'رمز التحقق' }}</title>

    <style>
        body{
            margin:0;
            padding:0;
            background:#f4f7fb;
            font-family:Tahoma,Arial,sans-serif;
            color:#333;
        }

        .wrapper{
            width:100%;
            padding:40px 15px;
        }

        .container{
            max-width:650px;
            margin:auto;
            background:#ffffff;
            border-radius:15px;
            overflow:hidden;
            box-shadow:0 10px 30px rgba(0,0,0,.08);
        }

        .header{
            background:linear-gradient(135deg,#2563eb,#1d4ed8);
            padding:35px;
            text-align:center;
            color:#fff;
        }

        .logo{
            width:70px;
            height:70px;
            margin:auto;
            border-radius:50%;
            background:white;
            color:#2563eb;
            display:flex;
            justify-content:center;
            align-items:center;
            font-size:34px;
            font-weight:bold;
        }

        .header h1{
            margin-top:18px;
            margin-bottom:0;
            font-size:28px;
        }

        .content{
            padding:40px;
            text-align:center;
        }

        .content h2{
            margin-bottom:15px;
            color:#1e293b;
        }

        .content p{
            color:#555;
            line-height:1.9;
            font-size:16px;
        }

        .code{
            display:inline-block;
            margin:30px 0;
            padding:18px 45px;
            border:2px dashed #2563eb;
            border-radius:10px;
            background:#f8fbff;
            font-size:40px;
            font-weight:bold;
            color:#2563eb;
            letter-spacing:8px;
        }

        .expire{
            background:#eef5ff;
            color:#1d4ed8;
            padding:15px;
            border-radius:8px;
            margin-top:20px;
            font-weight:bold;
        }

        .warning{
            margin-top:25px;
            padding:18px;
            background:#fff8e8;
            border:1px solid #facc15;
            border-radius:8px;
            color:#92400e;
            line-height:1.8;
        }

        .footer{
            background:#f8fafc;
            padding:25px;
            text-align:center;
            color:#888;
            font-size:14px;
        }

        @media(max-width:600px){

            .content{
                padding:25px;
            }

            .code{
                font-size:28px;
                padding:15px 20px;
                letter-spacing:5px;
            }

        }

    </style>

</head>
<body>

<div class="wrapper">

    <div class="container">

        <div class="header">

            <div class="logo">
                🔒
            </div>

            <h1>{{ config('app.name') }}</h1>

        </div>

        <div class="content">

            <h2>{{ $title ?? 'رمز التحقق' }}</h2>

            <p>
                {{ $introMessage ?? 'استخدم رمز التحقق التالي لإكمال العملية.' }}
            </p>

            <div class="code">
                {{ $code }}
            </div>

            <div class="expire">
                ينتهي هذا الرمز في
                <strong>{{ $expiresAt }}</strong>
            </div>

            <div class="warning">
                <strong>تنبيه أمني</strong><br>

                لا تشارك هذا الرمز مع أي شخص مهما كان،
                ولن يطلبه منك فريق الدعم أو أي موظف في
                {{ config('app.name') }}.
            </div>

        </div>

        <div class="footer">

            جميع الحقوق محفوظة © {{ date('Y') }}<br>

            {{ config('app.name') }}

        </div>

    </div>

</div>

</body>
</html>