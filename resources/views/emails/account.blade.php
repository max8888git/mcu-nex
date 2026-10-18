{{-- Account emails (App\Support\AccountEmail): confirm an address, reset a password. Inline styles for mail apps. --}}
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>{{ $heading }}</title></head>
<body style="margin:0;padding:24px 12px;background:#f5f5f9;font-family:Arial,Helvetica,sans-serif;color:#1f1230;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:520px;margin:0 auto;background:#ffffff;border:1px solid #e3d9f0;border-radius:12px;">
    <tr><td style="padding:18px 24px;background:#5c267e;border-radius:12px 12px 0 0;color:#ffffff;">
      <div style="font-size:16px;font-weight:bold;letter-spacing:1px;">MCU-NEX</div>
      <div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#e2d4ff;">Registrar Room Allocation</div>
    </td></tr>
    <tr><td style="padding:24px;">
      <h1 style="margin:0 0 12px;font-size:20px;color:#2d163f;">{{ $heading }}</h1>
      <p style="margin:0 0 20px;font-size:14px;line-height:1.6;">{{ $text }}</p>
      <p style="margin:0 0 20px;"><a href="{{ $url }}" style="display:inline-block;padding:11px 20px;background:#5c267e;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:bold;font-size:14px;">{{ $button }}</a></p>
      <p style="margin:0 0 6px;font-size:12px;color:#7a6992;">If the button doesn’t work, copy this link into your browser:</p>
      <p style="margin:0 0 20px;font-size:12px;word-break:break-all;"><a href="{{ $url }}" style="color:#5c267e;">{{ $url }}</a></p>
      <p style="margin:0;font-size:12px;color:#7a6992;">{{ $foot }}</p>
    </td></tr>
  </table>
</body>
</html>
