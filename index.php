<!DOCTYPE html>

<html>
    <head>
        <title>Sélecteur de livrée Raceroom</title>

        <meta name="viewport" content="width=device-width, initial-scale=1">

        <?php include("r3e_db_api.php"); ?>
        <script src="jquery-3.3.1.min.js"></script>
        <script src="jquery.blockUI.min.js"></script>
        <script src="letsCook.js"></script>
        <script src="urlTools.js"></script>

        <style>
            html {font-family: sans-serif; font-size: 90% }
            body {margin: 0px;}

            .header {box-sizing: border-box; position: sticky; overflow: auto; padding: 7px 7px; background-color: #ddd; width: 100%; top: 0; z-index: 999;}
            .homeImage {width:20px; height:20px; float:left; margin-right:8px; margin-top:7px}
            select {width: 300px; margin: 1px}
            .listPrompt {font-style: italic; color: #999;}
            .headerRightBox {float: right}
            .username {text-align:right; font-size:80%}
            .notification {background-color: #666; font-weight: bold; color:#ddd; position: absolute; right: 0; top:1.3em;padding: 3px; display: none}

            .thumbnail {position: relative; display: inline-block; cursor: pointer; width: 460px; height: 230px; background-color: #f2f2f2; margin: 2px 2px;}
            .thumbnail:hover {background-color: #fff;}
            .thumbnailNotOwned {position: relative; display: inline-block; width: 460px; height: 230px; background-color: #ccc; margin: 2px 2px;}
            .thumbnailNotOwned .thumbnailText {color:#777}
            .image {width: 460px; height: 230px; z-index:0}
            .thumbnailText {position: absolute; bottom: 10px; left: 0; width: 100%; text-align: center; color: #888; font-size: 90%; z-index:2}
            .thumbnail:hover .thumbnailText {color: #666;}
            .notSureIfOwned {position: absolute; top: 10px; right: 10px; color: #999; font-size: 130%; font-weight:bold; z-index:1}

            .splash {position: relative; top: 20px; text-align: center; color:#444}
            .tip {}

            .loginBox {display: none; margin-top: 50px; background-color:#f4f4f4; padding:4px}
            .loggedPrompt {margin-right:10px}
            .subLoginBox {margin-top: 10px}
            .loginForm {display: none}
            .openLoginFormLink {display:none; font-size:90%}
            .resyncButton {display:inline-block}
            .resyncTip {font-size:90%; font-style:italic; margin-top:6px; color:#666; }

            .profileHelpLink {display:block; margin-top: 40px; font-size:90%}
            .profileHelp {display:none; margin-top: 40px}
            
            .profileHelp img {margin: 10px 30px; border: 4px solid #ddd}
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
                    checkProfile(username, displayUrlData);
                else {
                    setUsername('');
                    initializeLoginBox();
                    displayUrlData();
                }

                window.onpopstate = handleHistoryChange;
                // history.pushState({ foo: 'fake' }, 'Fake Url', 'hy, this is a fake url.html');
            }

            function handleHistoryChange(event){
                event.preventDefault();
                displayUrlData();
            }

            function displayUrlData() { // TODO ça fait quoi si on met n'imp dans l'url ?
                var carId = getUrlParam("carId");
                var classId = getUrlParam("classId");

                if(classId != null) {
                    $('#carClassSelector').val(classId);
                    
                    if(carId != null) {
                        getCars(classId, function(){$('#carSelector').val(carId)});
                        getLiveries(carId);
                    }
                    else getCars(classId, function(){$('#carSelector').val(-1)});
                }
            }

            function openLoginFormClicked() {
                $('#openLoginFormLink').css("display", "none");
                $('#loginForm').css("display", "block");
                $('#profileField').focus();
            }

            function initializeLoginBox() {
                $('#loginBox').css("display", "block");

                if(globalUsername != "" && globalUsername != null) {
                    $('#loggedPrompt').html("Profil : <b>"+globalUsername+"</b>");
                    $('#resyncButton').css("display", "inline-block");
                    $('.resyncTip').css("display", "block");
                    $('#openLoginFormLink').css("display", "block");
                    $('#loginForm').css("display", "none");
                    $('#profileField').val("");
                } else {
                    $('#loggedPrompt').html("Profil :");
                    $('#resyncButton').css("display", "none");
                    $('.resyncTip').css("display", "none");
                    $('#loginForm').css("display", "block");
                }
            }

            function getCars(classId, handler=null) {
                if(classId < 0) return;
                $("#thumbnailContainer").empty();
                $("#carSelector").empty();

                $.ajax({
                    type: "GET",
                    url: "r3e_db_api.php",
                    data: "getData&classId=" + classId,
                    success: function(result) {
                        $("#carSelector").html(result);
                        if(handler != null) handler();
                    }
                });
            };

            function carSelected(carId) {
                var classId = $('#carClassSelector').val();
                history.pushState({ 'carId': carId }, '', '?classId='+classId+'&carId='+carId);
                getLiveries(carId);
            }

            function getLiveries(carId) {
                if(carId < 0) return;
                $("#thumbnailContainer").empty();
                
                $.ajax({
                    type: "GET",
                    url: "r3e_db_api.php",
                    data: "getData&carId=" + carId + "&username=" + globalUsername,
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

            function checkProfile(username, handler=null) {
                $.blockUI({ message: '<h1>Vérification du profil Raceroom en cours...</h1>', css: { backgroundColor: '#fff', color: '#444', 'border-style':'none'} });
                
                if(synchronizingProfile) return;
                synchronizingProfile = true;
                
                var syncTriggered = false;

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
                                syncTriggered = true;
                                // Doesn't exists in our DB so create profile.
                                synchronizeProfile(username, handler);
                        }
                    },
                    error: function (a, b, c) {
                        alert("Une erreur est survenue.");
                        setUsername("");
                    },
                    complete: function () {
                        if(!syncTriggered){
                            initializeLoginBox();
                            if (handler != null) handler();
                        }
                        
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

            function synchronizeProfile(username, handler) {
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
                        if (handler != null) handler();
                    }
                });
            }

            function setUsername (_username, updateCookie=true) {
                if (updateCookie) Cookie.setValue('username', _username);
                globalUsername = _username;
                var loginText = _username == "" ? "Aucun profil utilisé" : _username;
                $('#usernameField').html(loginText);
            }
            
            function showProfileHelp() {
                $('.profileHelp').css('display', 'block');
                $('.profileHelpLink').css('display', 'none');
            }

        </script>
    </head>

    <body onLoad="onPageLoaded()">

        <div class="header">
            <div class="headerRightBox">
                <div id="usernameField" class="username">...</div>
                <input id="linkField" type="text" readonly />
            </div>
            <span id="notification" class="notification">Lien copié dans le presse-papier !</span>
            
            <a href="."><img class="homeImage" src="images/home.png"/></a>
            <select id="carClassSelector" onChange="getCars(this.value)">
                <?php getClasses(); ?>
            </select>
            <select id="carSelector" onChange="carSelected(this.value)"></select>
        </div>

        <div id="thumbnailContainer">
            <div class="splash">
                <h1>Sélecteur de livrée Raceroom</h1>
                <div class="tip"><p><b>Cliquez une image et le lien sera copié dans le presse-papier, puis collez-le dans votre message du forum.</b><p>Si vous entrez votre nom de profil Raceroom, les livrées que vous possédez seront mises en avant. Il sera sauvegardé pour vos prochaines visites.</p><p>Il n'est actuellement pas toujours possible de savoir si vous possédez la livrée par défaut d'une voiture, un point d'interrogation le signale.</p></div>
                <div id="loginBox" class="loginBox">
                    <span id="loggedPrompt" class="loggedPrompt"></span>
                    <button id="resyncButton" onClick="resyncClicked()">Resynchroniser</button>
                    <div class="resyncTip">Resynchronisez votre profil si vous avez acheté de nouvelles livrées depuis votre dernière visite.</div>
                    <div class="subLoginBox">
                        <a id="openLoginFormLink" class="openLoginFormLink" onClick="openLoginFormClicked()" href="#">Changer de profil</a>
                        <div id="loginForm" class="loginForm">
                            <form onSubmit="loginClicked(event)">
                                <input id="profileField" type="text" placeholder="Nom du profil Raceroom" />
                                <button type="submit">Valider</button>
                            </form>
                            <a href="#" class="profileHelpLink" onClick="showProfileHelp()">Comment obtenir votre nom de profil ?</a>
                            <div class="profileHelp">
                                <p>Rendez-vous sur le <a href="http://game.raceroom.com/store/">magasin Raceroom</a>, identifiez-vous puis ouvrez les paramètres de compte :</p>
                                <img src="images/profileHelp1.png" />
                                <img src="images/profileHelp2.png" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </body>
</html>