<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Exit Document' }} - {{ $employeeName ?? 'Employee' }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f1f5f9;
            color: #1e293b;
            padding: 30px 15px;
            font-size: 13px;
            line-height: 1.65;
        }

        .document-wrapper {
            max-width: 850px;
            margin: 0 auto;
            background: #ffffff;
            padding: 45px 50px;
            border-radius: 8px;
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            position: relative;
            min-height: 1000px;
        }

        @media print {
            body {
                background: #ffffff;
                padding: 0;
                color: #000000;
            }
            .document-wrapper {
                box-shadow: none;
                border: none;
                padding: 10px 0;
                max-width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="no-print text-center mb-4">
        <button onclick="window.print()" class="btn btn-primary px-4 py-2 fw-bold shadow-sm" style="background-color: #1c3faa; border-color: #1c3faa;">
            🖨️ Print / Save Document (PDF)
        </button>
        <button onclick="window.close()" class="btn btn-outline-secondary px-3 py-2 ms-2">
            Close
        </button>
    </div>

    <div class="document-wrapper">
        {!! $renderedContent !!}
    </div>
</body>
</html>
