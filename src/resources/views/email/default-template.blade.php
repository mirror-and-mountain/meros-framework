<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="format-detection" content="telephone=no,address=no,email=no,date=no,url=no">
    <style>
        html,body{margin:0!important;padding:0!important;}
        body{width:100%!important;background-color:#f3f4f6;}
        body,table,td,p,a,li{font-family:{{ $fontFamily }}!important;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;mso-line-height-rule:exactly;}
        table{border-collapse:collapse;border-spacing:0;mso-table-lspace:0pt;mso-table-rspace:0pt;}
        img{border:0;outline:none;text-decoration:none;max-width:100%;height:auto;display:block;}
        a{color:#0b57d0;text-decoration:underline;}
        a[x-apple-data-detectors]{color:inherit!important;text-decoration:none!important;}
        @media only screen and (max-width:680px){
            .email-container{width:100%!important;}
            .email-col-table,
            .email-col-table tbody,
            .email-col-table tr,
            .email-row-table,
            .email-row-table tbody,
            .email-row-table tr{display:block!important;width:100%!important;}
            .email-col-table td,
            .email-col-cell,
            .email-row-cell{display:block!important;width:100%!important;max-width:100%!important;float:none!important;}
            .email-stack-group{display:block!important;}
            .email-gap-horizontal{margin-left:0!important;margin-top:var(--email-gap,16px)!important;}
            .email-row-cell-gap{padding-left:0!important;padding-top:var(--email-gap,20px)!important;}
            .email-col-gap{padding-left:0!important;padding-top:var(--email-gap,24px)!important;}
        }
    </style>
    <!--[if mso]>
    <style>
        .email-col-table,
        .email-col-table tbody,
        .email-col-table tr,
        .email-col-table td,
        .email-col-cell,
        .email-row-table,
        .email-row-table tbody,
        .email-row-table tr,
        .email-row-cell{display:block !important;width:100% !important;max-width:100% !important;}
        .email-stack-group{display:block !important;}
        .email-gap-horizontal{margin-left:0 !important;margin-top:16px !important;}
        .email-row-cell-gap{padding-left:0 !important;padding-top:20px !important;}
        .email-col-gap{padding-left:0 !important;padding-top:24px !important;}
    </style>
    <![endif]-->
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;mso-hide:all;">{{ $preheader }}</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f3f4f6;">
        <tr>
            <td align="center" style="padding:0;">
                <!--[if (gte mso 9)|(IE)]>
                <table role="presentation" width="680" cellpadding="0" cellspacing="0" border="0"><tr><td>
                <![endif]-->
                <table class="email-container" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:680px;background-color:#ffffff;">
                    {!! $bodyRows !!}
                </table>
                <!--[if (gte mso 9)|(IE)]>
                </td></tr></table>
                <![endif]-->
            </td>
        </tr>
    </table>
</body>
</html>