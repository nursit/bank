# Plugin Bank v4 pour SPIP <small>Paiement bancaire</small>

Ce plugin permet de gérer les interfaces techniques avec les prestataires bancaires.

Une table des transactions permet de conserver un historique et l'etat de chaque paiement ou demande de paiement.
Le plugin ne fournit pas un processus de paiement complet côté front, il ne prend en charge que la partie technique et est utilisé par d'autres plugins comme

* le plugin Souscriptions pour la gestion de dons et adhésions https://github.com/otetard/spip_souscription
* le plugin Paiement avec formidable http://plugins.spip.net/formidablepaiement
* le plugin Commandes http://plugins.spip.net/commandes

Il peut aussi être complété par

* le plugin factures https://github.com/nursit/factures




### Changelog

* Version 4 du plugin
  * Nécessite SPIP 3.1+, compatible SPIP 3.2
  * Mise en conformité de l'implémentation de Stripe avec les normes Strong Customer Authentication qui entrent en vigueur en septembre 2019
    * seul le paiement à l'acte est implémenté, les nouveaux paiements récurrents ne sont plus possibles (TODO)
  * Suppression du prestataire Internet+ (code non maintenu, non testé en situation réelle depuis trop longtemps)
  * Passage a la plateforme Monetico au lieu de CMCIC (rien a reconfigurer, c'est un switch transparent)
  
* Version 3 du plugin
  * Nécessite SPIP 3.0+, compatible SPIP 3.1 et SPIP 3.2
  * Refonte de la configuration : 
    * on peut avoir plusieurs modules du même prestataire technique avec des paramètres différents
    * possibilité de configurer l'ordre de présentation des modes de paiement
    * possibilité de configurer les CB proposées pour les prestataires par CB qui le permettent (tous sauf SIPS)
    * conervation de la clé de test et passage en test/production par case a cocher
  * Ajout du prestataire PayZen qui supporte le paiement par SEPA pour les paiements uniques et les paiements récurrents
  * Evolution de l'API abonnements, prise en charge des récurences plus complexes (N1 paiements initiaux, N2 paiements suivants)
  * Ajout d'un statut "attente" sur les transactions pour le paiement par chèque, virement et SEPA, et ajout d'une page de retour bank_retour_attente pour le retour sur ces transactions
  * Prise en charge de PDT pour paypal


## Prestataires pris en charge

La configuration permet d'ajouter/supprimer/ordonner les prestataires bancaires que l'on souhaite utiliser.
Il est possible d'avoir plusieurs configurations pour le meme prestataire technique.

Le paiement par SEPA est pris en charge via PayZen.

### Paiements à l'acte

Le plugin permet le paiement à l'acte via les plateformes techniques suivantes :

* Monetico (C.I.C, Crédit Mutuel)
* Ogone
* Paybox
* Paypal (par formulaire simple ou Paypal Express)
* SIPS (Elysnet, Mercanet, Scellius, Sogenactif)
* SIPSv2 (Mercanet, Scellius, Sogenactif)
* Stripe
* SystemPay (Banque Populaire CyberPlus, O.S.B., SystemPay et SP Plus)
* PayZen

Par ailleurs, il est aussi possible d'utiliser les modes de paiement suivant :

* Chèque
* Virement

Un mode de paiement "Simulation" permet de tester le workflow de paiement sans prestataire bancaire dans la phase de développement.
Il utilise tout les même processus que le paiement par un prestataire en by-passant simplement celui-ci.

### Paiements récurrents

Le plugin permet aussi les paiements mensuels avec les plateformes techniques suivantes :

* Paybox
* PayZen
* Stripe [ Deprecated : plus de nouveau paiement par abonnement, a refaire suite à la mise en place des SCA https://stripe.com/docs/billing/migration/strong-customer-authentication]

Un mode de paiement "Simulation" permet de tester le workflow de paiement pendant la phase de developpement. 

Les documentations (pdf) des différentes plateformes sont centralisées à cette adresse : http://www.nursit.com/doc_presta_bank .

