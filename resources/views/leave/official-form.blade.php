<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LMPC Leave Application Form</title>
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0 auto; max-width: 273mm; color: #111; background: white; font: 14px/1.35 Arial, sans-serif; }
        .paper { padding: 16px; }
        .heading { display: grid; grid-template-columns: 1fr auto 1fr; align-items: baseline; gap: 8px; margin-bottom: 10px; }
        h1 { font-size: 20px; margin: 0; text-align: center; }
        .reference { text-align: right; }
        .frame { position: relative; isolation: isolate; border: 3px double #111; }
        .form-watermark {
            position: absolute;
            z-index: -1;
            inset: 0;
            margin-inline: auto;
            width: 44%;
            height: 100%;
            object-fit: contain;
            opacity: 0.10;
            pointer-events: none;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }
        .application, .block { padding: 16px; }
        .fields, .decision { display: grid; grid-template-columns: 1fr 1fr; }
        .fields { gap: 0 24px; }
        .entry { display: grid; grid-template-columns: 110px minmax(0, 1fr); gap: 8px; margin: 0 0 6px; }
        .value { border-bottom: 1px solid; overflow-wrap: anywhere; margin: 0; min-height: 19px; }
        .options { display: flex; align-items: center; flex-wrap: wrap; gap: 8px 24px; margin: 16px 0; }
        .choice { display: inline-flex; align-items: baseline; gap: 6px; }
        .box { display: inline-flex; flex: 0 0 auto; align-items: center; justify-content: center; vertical-align: middle; width: 20px; height: 14px; border: 1.5px solid; font-size: 13px; font-weight: bold; }
        .other-value { flex: 1 1 100px; }
        .reason { white-space: pre-wrap; min-height: 3.5em; }
        .employee-line { max-width: 230px; margin: 20px 0 0 auto; padding-top: 3px; border-top: 1px solid; text-align: center; font-size: 12px; }
        .supervisor-section { break-inside: avoid; }
        .instruction { border-block: 3px double #111; padding: 4px 12px; margin: 0; font-weight: bold; font-style: italic; }
        .block { min-height: 155px; }
        .block + .block { border-left: 3px double #111; }
        .block h2 { font-size: 14px; margin: 0 0 12px; }
        .signature { width: min(100%, 320px); text-align: center; }
        .signature-image { display: grid; place-items: center; min-height: 98px; padding: 5px 8px; }
        .signature-image img { display: block; width: 100%; height: 88px; object-fit: contain; }
        .signature-caption { border-top: 1px solid; padding-top: 4px; overflow-wrap: anywhere; }
        .signature-caption strong { display: block; }
        .footnote { margin: 8px 0 0; font-size: 11px; }
        .status { margin: 0 0 12px; font-weight: bold; }
        @media print {
            .paper { padding: 0; }
            .application, .block { padding: 6mm; }
            .block { min-height: 47mm; }
        }
        @media screen and (max-width: 510px) {
            .paper, .application, .block { padding: 10px; }
            .fields { grid-template-columns: 1fr; }
            .heading { grid-template-columns: 1fr 1fr; }
            h1 { grid-row: 2; grid-column: 1 / -1; font-size: 17px; }
            .entry { grid-template-columns: 100px minmax(0, 1fr); }
            .block h2 { font-size: 11px; }
        }
    </style>
</head>
<body>
<main class="paper">
    @if(isset($status))<p class="status">{{ $status }}</p>@endif
    <header class="heading">
        <strong>LMPC</strong>
        <h1>LEAVE APPLICATION FORM</h1>
        <span class="reference">{{ $templateReference }}</span>
    </header>
    <div class="frame">
        <img class="form-watermark" src="{{ $watermarkSource }}" alt="">
        <section class="application" aria-label="Employee application">
            <dl class="fields">
                @foreach(['Name' => 'name', 'Date Filed' => 'filed', 'Sect/Dept/Div.' => 'department', 'Inclusive Dates' => 'dates', 'No. of Days' => 'days'] as $label => $field)
                    <div class="entry"><dt>{{ $label }}</dt><dd class="value">{{ $fields[$field] }}</dd></div>
                @endforeach
            </dl>
            <div class="options" aria-label="Leave type">
                @foreach(['vacation' => 'Vacation Leave', 'sick' => 'Sick Leave', 'other' => 'Others (pls specify)'] as $category => $label)
                    <span class="choice"><span class="box" role="img" aria-label="{{ $fields['category'] === $category ? 'Selected' : 'Unselected' }}">{{ $fields['category'] === $category ? '✓' : '' }}</span> <strong>{{ $label }}</strong></span>
                @endforeach
                <span class="value other-value">{{ $fields['category'] === 'other' ? $fields['other'] : '' }}</span>
            </div>
            <dl class="entry"><dt>Reason</dt><dd class="value reason">{{ $fields['reason'] }}</dd></dl>
            <p class="employee-line">Signature of Employee</p>
        </section>
        <section class="supervisor-section" aria-label="Supervisor decision">
            <p class="instruction">To be filled out by immediate Head:</p>
            <div class="decision">
                <div class="block">
                    <h2><span class="box" role="img" aria-label="{{ isset($approval) ? 'Selected' : 'Unselected' }}">{{ isset($approval) ? '✓' : '' }}</span> AUTHORIZED LEAVE</h2>
                    <strong>Approved by:</strong>
                    <div class="signature">
                        <div class="signature-image">
                            @isset($approval)<img src="data:image/png;base64,{{ $approval['signature'] }}" alt="Supervisor signature">@endisset
                        </div>
                        <div class="signature-caption">
                            @isset($approval)<strong>{{ $approval['name'] }}</strong>@endisset
                            Immediate/Dept. Head
                        </div>
                    </div>
                </div>
                <div class="block">
                    <h2><span class="box" role="img" aria-label="Unselected"></span> UNAUTHORIZED LEAVE</h2>
                    <strong>Noted by:</strong>
                    <div class="signature">
                        <div class="signature-image"></div>
                        <div class="signature-caption">Immediate/Dept. Head</div>
                    </div>
                </div>
            </div>
        </section>
    </div>
    @isset($approval)
        <p class="footnote">Document {{ $approval['reference'] }} · Approved {{ $approval['signedAt'] }}@if($approval['chargedDays'] !== null) · Chargeable days {{ $approval['chargedDays'] }}@endif</p>
    @endisset
</main>
</body>
</html>
