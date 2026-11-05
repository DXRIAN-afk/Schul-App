<?php
function page_header(string $title, bool $compact = false): void
{
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Frame-Options: DENY');
    ?>
<!DOCTYPE html>
<html lang="de-CH">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#4f46e5">
<meta name="robots" content="noindex">
<title><?= e($title) ?> – SchulOrganizer</title>
<link rel="icon" href="/favicon.ico">
<link rel="manifest" href="/manifest.webmanifest">
<style>
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f8fafc;color:#0f172a;display:flex;align-items:<?= $compact ? 'flex-start' : 'center' ?>;justify-content:center;padding:<?= $compact ? '16px' : '24px' ?>}
  .card{width:100%;max-width:<?= $compact ? '640px' : '380px' ?>;background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:28px;box-shadow:0 1px 3px rgba(15,23,42,.06)}
  .brand{display:flex;align-items:center;gap:10px;margin-bottom:20px}
  .logo{width:34px;height:34px;border-radius:10px;background:#4f46e5;color:#fff;font-weight:700;display:flex;align-items:center;justify-content:center}
  .brand span{font-weight:600;font-size:18px}
  h1{font-size:20px;margin:0 0 6px}
  p.sub{margin:0 0 20px;color:#64748b;font-size:14px}
  label{display:block;font-size:13px;font-weight:500;margin:14px 0 6px;color:#334155}
  input[type=text],input[type=password]{width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:10px;font-size:15px;background:#fff;color:inherit}
  input:focus{outline:2px solid #6366f1;outline-offset:0;border-color:#6366f1}
  button,.btn{display:inline-block;width:100%;margin-top:20px;padding:11px 14px;border:0;border-radius:10px;background:#4f46e5;color:#fff;font-size:15px;font-weight:600;cursor:pointer;text-align:center;text-decoration:none}
  button:hover,.btn:hover{background:#4338ca}
  .btn.secondary{background:#e2e8f0;color:#0f172a}
  .btn.secondary:hover{background:#cbd5e1}
  .alt{margin-top:18px;font-size:14px;color:#64748b;text-align:center}
  a{color:#4f46e5}
  .error{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:10px 12px;border-radius:10px;font-size:14px;margin-bottom:6px}
  .success{background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;padding:10px 12px;border-radius:10px;font-size:14px;margin-bottom:6px}
  .stats{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin:16px 0}
  .stat{border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;background:#f8fafc}
  .stat .n{font-size:26px;font-weight:700;color:#4f46e5}
  .stat .l{font-size:12px;color:#64748b}
  table{width:100%;border-collapse:collapse;font-size:13px}
  th,td{text-align:left;padding:7px 6px;border-bottom:1px solid #e2e8f0;white-space:nowrap}
  th{color:#64748b;font-weight:500}
  .table-wrap{overflow-x:auto}
  .dot{display:inline-block;width:8px;height:8px;border-radius:50%;background:#cbd5e1;margin-right:6px}
  .dot.on{background:#22c55e}
  .row{display:flex;gap:8px}
  .row .btn{margin-top:12px}
  @media (prefers-color-scheme: dark){
    body{background:#020617;color:#f1f5f9}
    .card{background:#0f172a;border-color:#1e293b}
    label{color:#cbd5e1}
    input[type=text],input[type=password]{background:#020617;border-color:#334155}
    .stat{background:#020617;border-color:#1e293b}
    th,td{border-color:#1e293b}
    .btn.secondary{background:#1e293b;color:#f1f5f9}
    .btn.secondary:hover{background:#334155}
    a{color:#818cf8}
  }
</style>
</head>
<body>
<main class="card">
  <div class="brand"><div class="logo">S</div><span>SchulOrganizer</span></div>
<?php
}

function page_footer(): void
{
    echo "</main>\n</body>\n</html>\n";
}
