<?php

declare(strict_types=1);

$pageTitle = isset($pageTitle) && is_string($pageTitle) ? $pageTitle : 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($pageTitle) ?> | Design24 Studio</title>
    <style>
        :root { --green: #07553e; --dark: #063d2e; --cream: #f7f3ec; --white: #fff; --text: #202925; --line: #d9e2de; }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--cream); color: var(--text); font-family: Arial, sans-serif; line-height: 1.5; }
        a { color: inherit; }
        button, input, select, textarea { font: inherit; }
        .admin-header { display: flex; min-height: 72px; padding: 12px clamp(20px, 5vw, 72px); align-items: center; justify-content: space-between; background: var(--dark); color: var(--white); }
        .admin-brand { font-size: 1.1rem; font-weight: 700; text-decoration: none; }
        .admin-user { display: flex; align-items: center; gap: 18px; }
        .admin-user form { margin: 0; }
        .logout-button { padding: 9px 15px; border: 1px solid rgba(255,255,255,.6); border-radius: 4px; background: transparent; color: var(--white); cursor: pointer; }
        .admin-main { width: min(1080px, calc(100% - 32px)); margin: 42px auto; }
        .panel { padding: clamp(22px, 4vw, 38px); border: 1px solid var(--line); border-radius: 8px; background: var(--white); box-shadow: 0 12px 32px rgba(6,61,46,.07); }
        .placeholder-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px; margin-top: 28px; }
        .placeholder { display: block; padding: 25px; border: 1px solid var(--line); border-radius: 6px; background: #fbfcfb; text-decoration: none; }
        .placeholder[href]:hover { border-color: var(--green); box-shadow: 0 8px 22px rgba(6,61,46,.08); }
        .placeholder p { margin-bottom: 0; color: #68736e; }
        .login-shell { display: grid; min-height: 100vh; padding: 20px; place-items: center; }
        .login-card { width: min(440px, 100%); padding: 34px; border-radius: 8px; background: var(--white); box-shadow: 0 18px 50px rgba(6,61,46,.12); }
        .field { margin-top: 18px; }
        label { display: block; margin-bottom: 7px; font-weight: 700; }
        input[type="text"], input[type="email"], input[type="url"], input[type="number"], input[type="password"], input[type="file"] { width: 100%; min-height: 46px; padding: 9px 12px; border: 1px solid #aebcb6; border-radius: 4px; }
        select { width: 100%; min-height: 46px; padding: 9px 12px; border: 1px solid #aebcb6; border-radius: 4px; background: #fff; }
        input:focus { outline: 3px solid rgba(7,85,62,.18); border-color: var(--green); }
        textarea { width: 100%; min-height: 115px; padding: 10px 12px; resize: vertical; border: 1px solid #aebcb6; border-radius: 4px; }
        textarea:focus { outline: 3px solid rgba(7,85,62,.18); border-color: var(--green); }
        .primary-button { width: 100%; min-height: 48px; margin-top: 22px; border: 0; border-radius: 4px; background: var(--green); color: var(--white); font-weight: 700; cursor: pointer; }
        .error { padding: 12px 14px; border-left: 4px solid #a12626; background: #fff0f0; color: #7b1e1e; }
        .success { padding: 12px 14px; border-left: 4px solid var(--green); background: #edf9f4; color: var(--dark); }
        .settings-toolbar { display: flex; margin-bottom: 22px; align-items: center; justify-content: space-between; gap: 15px; }
        .settings-toolbar a { color: var(--green); font-weight: 700; }
        .settings-section { margin-top: 24px; padding: 24px; border: 1px solid var(--line); border-radius: 7px; background: #fcfdfc; }
        .settings-section h2 { margin-top: 0; }
        .settings-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 22px; }
        .checkbox-field { display: flex; margin-top: 18px; align-items: flex-start; gap: 10px; }
        .checkbox-field input { width: 18px; height: 18px; margin-top: 3px; }
        .checkbox-field label { margin: 0; }
        .help { display: block; margin-top: 6px; color: #68736e; font-size: .88rem; }
        .logo-preview { display: block; max-width: 280px; max-height: 120px; margin-top: 12px; object-fit: contain; border: 1px solid var(--line); background: #fff; }
        .form-actions { display: flex; margin-top: 28px; align-items: center; gap: 12px; flex-wrap: wrap; }
        .form-actions .primary-button { width: auto; margin: 0; padding: 0 24px; }
        .secondary-admin-button { display: inline-flex; min-height: 48px; padding: 0 20px; align-items: center; border: 1px solid #aebcb6; border-radius: 4px; background: #fff; color: var(--text); text-decoration: none; }
        .error-list { margin: 0; padding-left: 20px; }
        .settings-links { display: flex; margin-top: 18px; flex-wrap: wrap; gap: 10px; }
        .settings-links a { padding: 8px 12px; border: 1px solid var(--line); border-radius: 4px; color: var(--green); font-weight: 700; text-decoration: none; }
        .settings-links a:hover { border-color: var(--green); }
        @media (max-width: 650px) { .placeholder-grid, .settings-grid { grid-template-columns: 1fr; } .admin-user span { display: none; } .settings-toolbar { align-items: flex-start; flex-direction: column; } .settings-section { padding: 18px; } }
    </style>
</head>
<body>
