<!DOCTYPE html>

<html>
    <head>
        <title>Sélecteur de livrée Raceroom</title>

        <meta name="viewport" content="width=device-width, initial-scale=1">

        <?php include("r3e_db_api.php"); ?>
        <script src="jquery-3.3.1.min.js"></script>
        <script src="jquery.blockUI.min.js"></script>
        <script src="letscook.js"></script>

        <style>
            html {font-family: sans-serif; font-size: 90% }
            body {margin: 0px;}

            .header {box-sizing: border-box; position: sticky; overflow: auto; padding: 7px 7px; background-color: #ddd; width: 100%; top: 0; z-index: 999;}
            select {width: 300px; margin: 1px}
            .listPrompt {font-style: italic; color: #999;}
            .headerRightBox {float: right}
            .username {text-align:right;}
            .notification {background-color: #666; font-weight: bold; color:#ddd; position: absolute; right: 0; top:1em; margin-top:9px; padding: 3px; display: none}

            .thumbnail {position: relative; display: inline-block; cursor: pointer; width: 460px; height: 230px; background-color: #f2f2f2; margin: 2px 2px;}
            .thumbnail:hover {background-color: #fff;}
            .thumbnailNotOwned {position: relative; display: inline-block; width: 460px; height: 230px; background-color: #ccc; margin: 2px 2px;}
            .thumbnailNotOwned .thumbnailText {color:#777}
            .image {width: 460px; height: 230px; z-index:0}
            .thumbnailText {position: absolute; bottom: 10px; left: 0; width: 100%; text-align: center; color: #888; font-size: 90%; z-index:2}
            .thumbnail:hover .thumbnailText {color: #666;}
            .notSureIfOwned {position: absolute; top: 10px; right: 10px; color: #999; font-size: 130%; font-weight:bold; z-index:1}

            .splash {position: relative; top: 100px; text-align: center}
            .tip {text-align: center; font-style: italic}

            .loginBox {display: none; margin-top: 50px}
            .subLoginBox {margin-top: 20px}
            .loginForm {display: none}
            .openLoginFormLink {display: none;}
            .resyncButton {display:inline-block}
        </style>

        <script>

            // alert('username: '+Cookie.getValue('username'));
            // Cookie.setValue('username', 'gfdgdfgoo');

            timeoutId = -1;
            synchronizingProfile = false;
            globalUsername = null;

            function onPageLoaded() {
                var username = Cookie.getValue('username');

                if(username != null && username != '')
                    checkProfile(username);
                else
                    initializeLoginBox();
            }

            function openLoginFormClicked() {
                $('#openLoginFormLink').css("display", "none");
                $('#loginForm').css("display", "block");
            }

            function initializeLoginBox() {
                $('#loginBox').css("display", "block");

                if(globalUsername != "" && globalUsername != null) {
                    $('#loggedPrompt').html("Identifié avec le nom d'utilisateur '"+globalUsername+"'.");
                    $('#resyncButton').css("display", "inline-block");
                    $('#openLoginFormLink').css("display", "block");
                    $('#loginForm').css("display", "none");
                    $('#profileField').val("");
                } else {
                    $('#loggedPrompt').html("Pour distinguer les livrées que vous possédez, veuillez entrer votre nom de profil Raceroom.");
                    $('#resyncButton').css("display", "none");
                    $('#loginForm').css("display", "block");
                }
            }

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
                    data: "carId=" + carId + "&username=" + globalUsername,
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

            function notifyCopy() {
                $('#notification').css('display', 'inline');
                if (timeoutId != -1) clearTimeout(timeoutId);
                timeoutId = setTimeout(removeNotification, 2000);
            }

            function removeNotification() {
                $('#notification').css('display', 'none');
            }

            function checkProfile(username) {
                $.blockUI({ message: '<h1>Vérification du profil Raceroom en cours...</h1>', css: { backgroundColor: '#fff', color: '#444', 'border-style':'none'} });
                
                if(synchronizingProfile) return;
                synchronizingProfile = true;
                
                var initLoginBox = true;

                $.ajax({
                    type: "GET",
                    url: "user_profile_api.php",
                    data: "checkUsername=" + username,
                    success: function(result) {
                        synchronizingProfile = false;
                        $.unblockUI();

                        switch(result) {
                            case '0':
                                setUsername(username);
                            break;
                            case '1':
                                initLoginBox = false;
                                // Doesn't exists in our DB so create profile.
                                synchronizeProfile(username);
                        }
                    },
                    error: function (a, b, c) {
                        alert("Une erreur est survenue.");
                        setUsername("");
                    },
                    complete: function () {
                        if(initLoginBox) initializeLoginBox();
                    }
                });
            }
            
            function loginClicked(event) {
                event.preventDefault();
                
                var username = $("#profileField").val();
                if(username != "" && username != null)
                    checkProfile(username);
            }

            function resyncClicked() {
                if(globalUsername != "" && globalUsername != null)
                    synchronizeProfile(globalUsername);
            }

            function synchronizeProfile(username) {
                $.blockUI({ message: '<h1>Synchronisation du profil Raceroom en cours...</h1>', css: { backgroundColor: '#fff', color: '#444', 'border-style':'none'} });
                
                if(synchronizingProfile) return;
                synchronizingProfile = true;
                
                $.ajax({
                    type: "GET",
                    url: "user_profile_api.php",
                    data: "username=" + username,
                    success: function(result) {
                        switch (result) {
                            case '1':
                                alert("L'utilisateur '"+username+"' n'a pas été trouvé sur la boutique Raceroom.");
                                setUsername("");
                                break;
                            case '2':
                            case '3':
                                alert("Une erreur code '"+result+"' s'est produite.");
                                setUsername("");
                                break;
                            default:
                                setUsername(result);
                        }
                    },
                    error: function (a, b, c) {
                        alert("Une erreur est survenue.");
                        setUsername("");
                    },
                    complete: function () {
                        synchronizingProfile = false;
                        $.unblockUI();
                        initializeLoginBox();
                    }
                });
            }

            function setUsername (_username) {
                Cookie.setValue('username', _username);
                globalUsername = _username;
                $('#usernameField').text(_username);
            }
    
        </script>
    </head>

    <body onLoad="onPageLoaded()">

        <div class="header">
            <div class="headerRightBox">
                <div id="usernameField" class="username"></div>
                <input id="linkField" type="text" readonly />
            </div>
            <span id="notification" class="notification">Lien copié dans le presse-papier !</span>
            
            <select name="carClassSelector" onChange="getCars(this.value)">
                <?php getClasses(); ?>
            </select>
            <select id="carSelector" onChange="getLiveries(this.value)"></select>
        </div>

        <div id="thumbnailContainer">
            <div class="splash">
                <p class="tip">Cliquez une image et le lien sera copié dans le presse-papier, puis collez-le dans votre message du forum.</p>
                <div id="loginBox" class="loginBox">
                    <span id="loggedPrompt"></span>
                    <button id="resyncButton" onClick="resyncClicked()">Resynchroniser</button>
                    <div class="subLoginBox">
                        <a id="openLoginFormLink" class="openLoginFormLink" onClick="openLoginFormClicked()" href="#">Changer de profil</a>
                        <form id="loginForm" class="loginForm" onSubmit="loginClicked(event)">
                            <input id="profileField" type="text" placeholder="Nom du profil Raceroom" />
                            <button type="submit">Valider</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </body>
</html>