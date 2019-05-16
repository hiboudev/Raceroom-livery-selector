<!DOCTYPE html>

<html>
    <head>
        <meta charset="utf-8">
        <link rel="icon" type="image/png" href="images/favicon.png" />

        <title>Administration du sélecteur de livrée</title>
        <meta name="description" content="Page d'administration du sélecteur de livrée" />

        <meta name="viewport" content="width=device-width, initial-scale=1">

        <script>
            function onPageLoaded() {
                if (typeof(EventSource) === "undefined") {
                    alert("Erreur : Votre navigateur internet ne supporte pas la fonctionnalité 'Server-Sent Events'.");
                }
            }

            function updateDB () {
                var source = new EventSource("build_r3e_database.php");
                source.onmessage = function(event) {
                    if (event.data == "COMPLETE")
                        source.close();
                    else
                        document.getElementById("outputField").innerHTML += event.data + "<br />";
                };
            }
        </script>
    </head>

    <body onLoad="onPageLoaded()">
            <button onclick="updateDB()">Mettre à jour la base de données</button>
            <div id="outputField">
            </div>
    </body>
</html>
