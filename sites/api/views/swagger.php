<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebIArtisan API - Documentation</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui.css">
    <link rel="icon" type="image/png" href="https://webiartisan.prigent.tech/favicon.png" />
    <style>
        :root {
            color-scheme: dark;
            --bg: #08111f;
            --bg-alt: #0f172a;
            --panel: rgba(15, 23, 42, 0.88);
            --text: #e5eefb;
            --muted: #9fb0c9;
            --primary: #22c55e;
            --secondary: #38bdf8;
            --border: rgba(148, 163, 184, 0.22);
            --radius-lg: 24px;
            --radius-md: 14px;
        }

        body {
            margin: 0;
            background: 
                radial-gradient(circle at top left, rgba(56, 189, 248, 0.12), transparent 34%),
                radial-gradient(circle at top right, rgba(34, 197, 94, 0.1), transparent 30%),
                linear-gradient(180deg, #0b1220, var(--bg));
            color: var(--text);
            font-family: Inter, system-ui, -apple-system, sans-serif;
            min-height: 100vh;
        }

        #swagger-ui {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Swagger UI Overrides */
        .swagger-ui {
            filter: invert(0); /* Ensure no inversion if browser does it */
        }
        
        .swagger-ui .info .title, .swagger-ui .info li, .swagger-ui .info p, .swagger-ui .info table, .swagger-ui .model-title, .swagger-ui .parameter__name, .swagger-ui .parameter__type, .swagger-ui .opblock .opblock-summary-path, .swagger-ui .opblock .opblock-summary-description {
            color: var(--text) !important;
        }

        .swagger-ui .info .title {
            font-size: 2.5rem;
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .swagger-ui .topbar {
            display: none; /* Hide default topbar */
        }

        .swagger-ui .scheme-container {
            background: transparent;
            box-shadow: none;
            padding: 20px 0;
            border-bottom: 1px solid var(--border);
        }

        /* Opblock styling (endpoints) */
        .swagger-ui .opblock {
            background: var(--panel) !important;
            border: 1px solid var(--border) !important;
            border-radius: var(--radius-md) !important;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2) !important;
            margin-bottom: 16px !important;
            backdrop-filter: blur(12px);
        }

        .swagger-ui .opblock .opblock-summary {
            border-bottom: 1px solid var(--border);
        }

        /* Method Badges */
        .swagger-ui .opblock-summary-method {
            border-radius: 8px !important;
            font-weight: 800 !important;
            min-width: 80px !important;
        }

        .swagger-ui .opblock.opblock-get .opblock-summary-method { background: var(--secondary) !important; color: #08111f !important; }
        .swagger-ui .opblock.opblock-post .opblock-summary-method { background: var(--primary) !important; color: #052e16 !important; }
        
        /* Buttons */
        .swagger-ui .btn {
            border-radius: 999px !important;
            font-weight: 700 !important;
            border: 1px solid var(--border) !important;
            background: rgba(15, 23, 42, 0.45) !important;
            color: var(--text) !important;
            transition: all 0.2s ease;
        }

        .swagger-ui .btn:hover {
            background: rgba(15, 23, 42, 0.8) !important;
            border-color: var(--primary) !important;
        }

        .swagger-ui .btn.execute {
            background: linear-gradient(135deg, var(--primary), #4ade80) !important;
            color: #052e16 !important;
            border: none !important;
        }

        .swagger-ui .btn.authorize {
            border-color: var(--primary) !important;
            color: var(--primary) !important;
            background: rgba(34, 197, 94, 0.1) !important;
        }
        .swagger-ui .btn.authorize svg { fill: var(--primary) !important; }

        /* Inputs */
        .swagger-ui input[type=text], .swagger-ui textarea {
            background: rgba(8, 17, 31, 0.85) !important;
            border: 1px solid var(--border) !important;
            border-radius: 12px !important;
            color: var(--text) !important;
            padding: 10px 14px !important;
        }

        /* Header / Logo Section */
        .webiartisan-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 30px 40px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            font-size: 1.5rem;
            text-decoration: none;
            color: var(--text);
        }
        .brand-badge {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #052e16;
            font-weight: 900;
        }
        .brand span { color: var(--primary); }

        .header-links a {
            color: var(--muted);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: color 0.2s;
        }
        .header-links a:hover { color: var(--text); }

        /* Tables & Models */
        .swagger-ui section.models {
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            background: var(--panel);
        }
        .swagger-ui .model-container {
            background: transparent !important;
        }

        /* Responses */
        .swagger-ui .responses-table, .swagger-ui .parameters-table {
            background: transparent !important;
        }
        
        /* Dark mode table fixes */
        .swagger-ui .opblock-body pre.microlight {
            background: #0b1220 !important;
            border-radius: 12px;
            border: 1px solid var(--border);
        }
    </style>
</head>
<body>
    <div class="webiartisan-header">
        <a href="https://webiartisan.prigent.tech" class="brand">
            <div class="brand-badge">W</div>
            WebI<span>Artisan</span> API
        </a>
        <div class="header-links">
            <a href="/api/status/public">Statut du service</a>
        </div>
    </div>

    <div id="swagger-ui"></div>

    <script src="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function() {
            const ui = SwaggerUIBundle({
                url: "/api/docs/spec",
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "BaseLayout",
                persistAuthorization: true,
                displayRequestDuration: true,
                filter: true
            });
            window.ui = ui;
        };
    </script>
</body>
</html>
