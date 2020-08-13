Workflow des paiements Payzen/SystemPay&Co

PayZen est l'implementation de reference pour les solutions Lyra

PAYMENT :
cas simple, paiement direct et immediat, on reçoit une notification serveur autoresponse en temps reel du paiement, puis l'utilisateur revient sur response avec donnees du paiement

Dans le cas SEPA on suppose que le paiement a bien été encaissé, mais a ce stade on a pas encore de numéro d'auth. Je suppose qu'on a une notification de paiement avec les infos d'autorisation quand le SEPA est reellement encaissé. A tester/confirmer

REGISTER_SUBSCRIBE :
PayZen crée un identifiant d'abonnement et un identifiant client, mais aucun paiement immédiat. La notification serveur sur autoresponse contient les numeros d'abonnements mais pas de données sur le paiement.
Il faut atterir sur la page bank_ok mais on a pas encore eu de paiement.
La fonction activer_abonnement() est appelée, elle doit verifier que la transaction a été payée ou non et agir en consequence (periode d'essai, ou message pour dire vous recevrez un mail d'activation des qu'on recevra le paiement).
Dans le cas CB, si on a pas mis de delai pour la mise en action de l'abonnement, la notif paiement arrive dans l'heure, sur autoresponse, si on a bien entré la configuration de URL serveur sur création de paiement récurent dans l'interface PayZen
Dans le cas SEPA, on a un dela de paiement de 13 jours calendaires mini. Du coup on ne recevra la notif de paiement que 13 jours plus tard.


REGISTER_PAY_SUBSCRIBE :
PayZen créé un identifiant d'abonnement et un identifiant client, et un paiement immédiat. Attention, ce paiement n'est techniquement pas compté dans l'abonnement. On triche donc en décalant le début de l'abonnement a +1 mois (ou +1 an) car ce sera en réalité la seconde échéance. De meme il faut decompter 1 sur le nombre d'echeances et nombre d'échéances initiales, ce qui peut etre un peu perturbant.
On reçoit donc une notification de paiement reussi avec les identifiants client et abonnement, et on appele activer_abonnement().

C'est le mode qu'on utilise pour les abonnements par CB, mais il n'est pas possible de l'utiliser pour les paiements par SEPA, on se rabat donc en REGISTER_SUBSCRIBE à +13j pour l'abonnement par SEPA


TIP : on peut simuler la sequence de paiement decomposee pour les abonnements avec les CB en modifiant l'appel dans presta/payzen/payer/abonnement.php L29 en remplaçant REGISTER_PAY_SUBSCRIBE par REGISTER_SUBSCRIBE. On se retrouve ainsi dans le meme scenario que SEPA mais avec une sequence qui suit dans l'heure au lieu d'attendre 13j entre les 2 notifications.