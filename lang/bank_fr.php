<?php

// This is a SPIP language file  --  Ceci est un fichier langue de SPIP

$GLOBALS[$GLOBALS['idx_lang']] = array(

	'abonnement_avec' => 'Abonnement par <i>@nom@</i>',
	'abonnement_choisissez_cb' => 'Choisissez votre carte bancaire&nbsp;:',
	'abonnement_par_cb' => 'avec ma carte bancaire',

	'bouton_enregistrer_reglement_cheque' => 'R&eacute;gler par ch&egrave;que',
	'bouton_enregistrer_reglement_ok' => 'Le r&eacute;glement a bien &eacute;t&eacute; re&ccedil;u',
	'bouton_enregistrer_reglement_virement' => 'R&eacute;gler par virement bancaire',
	'bouton_rembourser' => 'Marquer comme remboursée',

	'carte_bleu' => 'Carte Bleue',
	'choisissez_cb' => 'Choisissez votre carte bancaire&nbsp;:',
	'confirme_reglement_annule' => 'L\'op&eacute;ration a &eacute;t&eacute; annul&eacute;e. Aucun r&egrave;glement n\'a &eacute;t&eacute; r&eacute;alis&eacute;',
	'confirme_reglement_pris_en_compte' => 'Votre r&egrave;glement a bien &eacute;t&eacute; pris en compte, et nous vous en remercions.',

	'explication_page_configurer_paiement' => 'Configurez les syst&egrave;mes de paiement actifs',

	'info_1_transaction' => '1 transaction',
	'info_nb_transactions' => '@nb@ transactions',
	'info_cheque_etablir_ordre' => 'Ch&egrave;que &eacute;tabli &agrave; l\'ordre de &laquo;&nbsp;<i>@ordre@</i>&nbsp;&raquo;',
    'info_cheque_imprimer' => "Les instructions concernant l'établissement du chèque seront fournies après avoir cliqué sur 'Régler par chèque'.

Transaction&nbsp;: #@transaction@
_ Montant&nbsp;: @montant@",
    'info_cheque_envoyer' => 'Envoyez votre ch&egrave;que libell&eacute; en euros
-* &agrave; l\'ordre de &laquo;&nbsp;@ordre@&nbsp;&raquo;&nbsp;;
-* du montant exact&nbsp;;
-* compensable dans une agence bancaire situ&eacute;e en France&nbsp;;
-* accompagn&eacute; du num&eacute;ro de transaction &agrave; noter au dos du ch&egrave;que (pas sur la partie d&eacute;tachable).

Transaction&nbsp;: #@transaction@
_ Montant&nbsp;: @montant@',
	'info_cheque_envoyer_adresse' => 'Merci d\'envoyer votre ch&egrave;que à l\'adresse&nbsp;:',
	'info_mode_reglement_enregistre' => 'Nous avons bien enregistré votre choix de mode de r&egrave;glement.',
	'info_mode_test' => 'Mode TEST (paiement fictif)',
    'info_virement' => 'Vous pouvez payer par virement bancaire.
Les instructions concernant l’établissement du virement seront fournies après avoir cliqué sur ’Régler par virement bancaire’.

Transaction&nbsp;: #@transaction@
_ Montant&nbsp;: @montant@
',
    'info_virement_etablir' => '
Libellé de votre virement : #@transaction@
_ Montant&nbsp;: @montant@
_ Compte bancaire&nbsp;:
-* B&eacute;n&eacute;ficiaire&nbsp;: &laquo;&nbsp;@ordre@&nbsp;&raquo;
-* Banque&nbsp;: @banque@<br/>
@adressebanque@
-* IBAN&nbsp;: @iban@
-* BIC&nbsp;: @bic@',
	'label_actif' => 'Activer',
	'label_configuration_cheque_notice' => 'Information compl&eacute;mentaire affich&eacute;e',
	'label_configuration_cheque_ordre' => 'Ordre',
	'label_configuration_cheque_adresse' => 'Adresse o&ugrave; envoyer les ch&egrave;ques',
	'label_configuration_virement_banque' => 'Nom de la banque',
	'label_configuration_virement_bic' => 'BIC',
	'label_configuration_virement_iban' => 'IBAN',
	'label_configuration_virement_ordre' => 'Compte b&eacute;n&eacute;ficiaire&nbsp;:',
	'label_configuration_virement_adresse_banque' => 'Adresse de la banque',
	'label_configuration_virement_notice' => 'Remarques compl&eacute;mentaires&nbsp;:',
	'label_email_ticket_admin' => 'Email destinataire des tickets d\'achat',
	'label_email_from_ticket_admin' => 'Email <i>from</i> des tickets d\'achat',
	'label_enregistrer_reglement_reference' => 'Reference',
	'label_inactif' => 'D&eacute;sactiver',
	'label_mode_paiement' => 'Modes de paiements a l\'acte',
	'label_mode_paiement_abo' => 'Modes de paiements des abonnements',
	'label_mode_test' => 'Utiliser en mode TEST (aucun paiement r&eacute;el)',
	'label_notifications' => 'Notifications',
	'label_presta_abo_paybox' => 'Paybox <a href="http://www.paybox.com/">http://www.paybox.com/</a>',
	'label_presta_abo_simu' => 'Simulation (necessite une autorisation par define)',
	'label_presta_abo_internetplus' => 'Internet +',
	'label_presta_cheque' => 'Ch&egrave;ques (encaissement manuel)',
	'label_presta_cmcic' => 'CMCIC <a href="https://www.cmcicpaiement.fr/fr/">cmcicpaiement.fr</a>',
	'label_presta_cmcic_banque' => 'Banque',
	'label_presta_cyberplus' => 'CyberPlus',
	'label_presta_internetplus' => 'Internet +',
	'label_presta_ogone' => 'Ogone <a href="http://www.ogone.fr/">http://www.ogone.fr/</a>',
	'label_presta_paybox' => 'Paybox <a href="http://www.paybox.com/">http://www.paybox.com/</a>',
	'label_presta_paypal' => 'Paypal (faiblement s&eacute;curis&eacute;) <a href="http://www.paypal.fr/">http://www.paypal.fr/</a>',
	'label_presta_paypalexpress' => 'Paypal Express Checkout <a href="http://www.paypal.fr/">http://www.paypal.fr/</a>',
	'label_presta_simu' => 'Simulation (necessite une autorisation par define)',
	'label_presta_sips' => 'SIPS',
	'label_presta_sips_service' => 'Service',
	'label_presta_virement' => 'Virement bancaire',
	'label_remboursement_raison' => 'Raison du remboursement',
	'label_resilier_abonnement' => 'R&eacute;silier',

	'label_type_paiement_cb_generique' => 'Carte bancaire',
	'label_type_paiement_cheque' => 'Ch&egrave;que',
	'label_type_paiement_paypal' => 'Compte Paypal',
	'label_type_paiement_simu' => 'Paiement fictif',
	'label_type_paiement_virement' => 'Virement',

	'legend_sips_logo_page_paiement' => 'Logos page de paiement',

	'payer' => 'Payer',
	'payer_avec' => 'Payer avec <i>@nom@</i>',
	'payer_par_cheque' => 'Payer par ch&egrave;que :',
	'payer_par_carte_bancaire' => 'Payer par carte bancaire :',
	'payer_par_virement' => 'Payer par virement bancaire :',


	'titre_reglement_ok' => 'R&egrave;glement r&eacute;ussi',
	'titre_menu_configurer' => 'Paiements en ligne',
	'titre_page_configurer_paiement' => 'Paiements en ligne',
	'titre_menu_transactions' => 'Transactions',
	'titre_reglement_annule' => 'Annulation',

);
