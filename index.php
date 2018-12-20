<!DOCTYPE html>

<html>
    <head>
        <meta charset="utf-8">
        <link rel="icon" type="image/png" href="images/favicon.png" />

        <title>Sélecteur de livrée Raceroom</title>
        <meta name="description" content="Sélectionnez une livrée Raceroom pour l'intégrer sur un forum." />

        <meta name="viewport" content="width=device-width, initial-scale=1">

        <link rel="stylesheet" type="text/css" href="styles/style-1.0.9.css">

        <?php include "r3e_db_api.php";?>
        <script src="js/jquery-3.3.1.min.js"></script>
        <script src="js/jquery.blockUI.min.js"></script>
        <script src="js/letsCook-1.0.1.js"></script>
        <script src="js/urlTools.js"></script>
        <script src="js/ajaxManager-1.0.2.js"></script>
        <script src="js/yallext-1.0.0.min.js"></script>
        <script src="js/liverySelector-1.0.6.js"></script>
    </head>

    <body onLoad="onPageLoaded()">

        <div class="header">
            <div class="headerRightBox">
                <div class="usernameContainer"><a id="usernameField" class="username" href=".">...</a></div>
                <input id="linkField" type="text" readonly />
            </div>
            <span id="notification" class="notification">Lien copié dans le presse-papier !</span>

            <a href="."><img class="homeImage" src="images/home.png"/></a>
            <select id="carClassSelector" onChange="classSelected(this.value)">
                <?php getClasses();?>
            </select>
            <select id="carSelector" onChange="carSelected(this.value)"></select>
            <div class="shopUnavailableMessage">La boutique R3E est injoignable, les images ne pourront pas s'afficher.</div>
        </div>

        <div id="thumbnailContainer" class="thumbnailContainer">
            <div class="splash">
				<img src="images/favicon.png" />
                <h1 class="title">Sélecteur de livrée Raceroom</h1>
                <div><p><b>Choisissez une classe et une voiture, cliquez une image et le lien sera copié dans le presse-papier, puis collez-le dans votre message du forum.</b><p>Si vous entrez votre nom de profil Raceroom, les livrées que vous possédez seront mises en avant. Il sera sauvegardé pour vos prochaines visites.</p><p>Certaines classes, voitures et livrées "spéciales" ne sont pas ou plus achetables, elles sont en <span class="specialGrey">grisé</span> dans les listes et estampillées d'un <span class="special"></span> dans les livrées.</p></div>
                <div id="loginBox" class="loginBox">
                    <span id="loggedPrompt" class="loggedPrompt"></span>
                    <button id="resyncButton" onClick="resyncClicked()">Resynchroniser</button>
                    <div class="resyncTip">Resynchronisez votre profil si vous avez acheté de nouvelles livrées depuis votre dernière visite.</div>
                    <div class="subLoginBox">
                        <div class="openLoginFormLinkContainer"><a class="openLoginFormLink" onClick="openLoginFormClicked()" href="#">Changer de profil</a></div>
                        <div id="loginForm" class="loginForm">
                            <form onSubmit="loginClicked(event)">
                                <input id="profileField" type="text" placeholder="Nom du profil Raceroom" />
                                <button type="submit">Valider</button>
                            </form>
                            <div class="forgetProfileContainer"><a href="#" class="forgetProfile" onClick="forgetProfile()">N'utiliser aucun profil</a></div>
                            <div class="profileHelpLinkContainer"><a href="#" class="profileHelpLink" onClick="showProfileHelp()">Comment obtenir votre nom de profil ?</a></div>
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