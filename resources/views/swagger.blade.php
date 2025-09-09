<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>TourShop API Docs</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css" />
    <style>
        body {
            margin: 0;
        }

        #swagger-ui {
            max-width: 100%;
        }
    </style>
</head>

<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script>
        window.addEventListener('load', () => {
            // Détection automatique de l'URL de base
            const baseUrl = window.location.origin;
            const yamlUrl = baseUrl + '/openapi.yaml';

            window.ui = SwaggerUIBundle({
                url: yamlUrl,
                dom_id: '#swagger-ui',
                presets: [SwaggerUIBundle.presets.apis],
                layout: 'BaseLayout',
                docExpansion: 'none',
                deepLinking: true,
            });
        });
    </script>
</body>

</html>