<!DOCTYPE html>

<html>
    <head>
        <title>Sélecteur de livrée Raceroom</title>

        <meta name="viewport" content="width=device-width, initial-scale=1">

        <?php include("r3e_db_api.php"); ?>
        <script src="jquery-3.3.1.min.js"></script>

        <style>
            html {font-family: sans-serif; font-size: 90% }
            body {margin: 0px;}

            .header {box-sizing: border-box; position: sticky; background-color: #ddd; width: 100%; padding: 10px 5px; top: 0; z-index: 999;}
            select {width: 300px; margin: 1px}
            .listPrompt {font-style: italic; color: #999;}
            .linkField {float: right;}
            .notification {background-color: #666; font-weight: bold; color:#ddd; margin: 0; position: absolute; right: 0; padding: 3px; display: none}

            .thumbnail {position: relative; display: inline-block; cursor: pointer; width: 460px; height: 230px; background-color: #f2f2f2; margin: 2px 2px;}
            .thumbnail:hover {background-color: #fff;}
            .image {width: 460px; height: 230px;}
            .thumbnailText {position: absolute; bottom: 10px; left: 0; width: 100%; text-align: center; color: #888; font-size: 90%;}
            .thumbnail:hover .thumbnailText {color: #666;}

            .tip {text-align: center; font-style: italic; position: relative; top: 100px}
        </style>

        <script>
            function getCars(classId) {
                if(classId < 0) return;
                $("#thumbnailContainer").empty();
                $("#carSelector").empty();

                $.ajax({
                    type: "GET",
                    url: "r3e_db_api.php",
                    data: "classId=" + classId,
                    success: function(result) {
                        $("#carSelector").html(result);
                    }
                });
            };

            function getLiveries(carId) {
                if(carId < 0) return;
                $("#thumbnailContainer").empty();
                
                $.ajax({
                    type: "GET",
                    url: "r3e_db_api.php",
                    data: "carId=" + carId,
                    success: function(result) {
                        $("#thumbnailContainer").html(result);
                    }
                });
            }

            function copyLink(link) {
                $("#linkField").val("[IMG]" + link + "[/IMG]");
                $("#linkField").select();
                if (document.execCommand("copy")) {
                    notifyCopy();
                } else {
                    alert("Votre configuration n'autorise pas la copie dans le presse-papier, veuillez copier le lien manuellement.");
                }
            }
            
            timeoutId = -1;

            function notifyCopy() {
                $('#notification').css('display', 'inline');
                if (timeoutId != -1) clearTimeout(timeoutId);
                timeoutId = setTimeout(removeNotification, 2000);
            }

            function removeNotification() {
                $('#notification').css('display', 'none');
            }
    
        </script>
    </head>

    <body>

        <div class="header">
            <input id="linkField" class="linkField" type="text" readonly />
            <span id="notification" class="notification">Lien copié dans le presse-papier !</span>
            <select name="carClassSelector" onChange="getCars(this.value)">
                <?php getClasses(); ?>
            </select>
            <select id="carSelector" onChange="getLiveries(this.value)"></select>
        </div>

        <div id="thumbnailContainer">
            <p class="tip">Cliquez une image et le lien sera copié dans le presse-papier, puis collez-le dans votre message du forum.</p>
        </div>

    </body>
</html>