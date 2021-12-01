<?php

// Ceci est un fichier langue de SPIP -- This is a SPIP language file
// Produit automatiquement par le plugin LangOnet à partir de la langue source fr
// Module: bank
// Langue: en
// Date: 29-05-2015 10:10:17
// Items: 109

if (!defined('_ECRIRE_INC_VERSION')) return;

$GLOBALS[$GLOBALS['idx_lang']] = array(

// A
	'abonnement_avec' => 'Subscription with <i>@nom@</i>',
	'abonnement_choisissez_cb' => 'Select your Credit Card:',
	'abonnement_par_carte_bancaire' => 'Subscription with Credit Card:',
	'abonnement_par_cb' => 'with Credit Card',
	'abonnement_par_prelevement_sepa' => 'Subscription with direct account debit (SEPA):',

// B
	'bouton_descendre' => 'down',
	'bouton_enregistrer_reglement_carte' => 'Pay by card',
	'bouton_enregistrer_reglement_cheque' => 'Pay by check',
	'bouton_enregistrer_reglement_ok' => 'Record payment achieved',
	'bouton_enregistrer_reglement_virement' => 'Pay by bank transfer',
	'bouton_monter' => 'up',
	'bouton_rembourser' => 'Record as refunded',
	'bouton_abondonner_transaction' => 'Cancel this transaction',

// C
	'carte_bleu' => 'Credit Card',
	'choisissez_cb' => 'Select your Credit Card:',
	'confirme_reglement_annule' => 'The operation was cancelled. No payment has been achieved',
	'confirme_reglement_pris_en_compte' => 'Your payment has been taken into account, thank you.',
	'confirme_reglement_attente' => 'Your payment is pending, we will inform you as soon as recieved.',

// E
	'erreur_aucun_moyen_paiement' => 'No payment method available',
	'erreur_confirmer_montant_reglement_different' => 'Confirm that the amount is different from expected',
	'erreur_transaction_echec' => 'No payment has been achieved. (Transaction Ref. @ref@)',
	'erreur_transaction_invalide' => 'An error has occured due to unexpected received data.',
	'erreur_transaction_traitement_incomplet' => 'Processing of the payment for this transaction has been interrupted. Manual repair of database is required.',
	'erreur_serveur_indisponible' => 'An error occured or the payment service is not answering.',
	'erreur_ressayer_plus_tard' => 'Retry later',
	'explication_enregistrer_reglement_montant' => 'Correct if not matching the effective paid amount',
	'explication_page_configurer_paiement' => 'Set up active payment systems',

// I
	'info_aucune_transaction' => 'No transaction',
	'info_1_transaction' => '1 transaction',
	'info_cheque_envoyer' => 'Send your check made out in Euros
-* to the order of “<mark>@ordre@</mark>”
-* for the exact amount <mark>@montant@</mark>
-* drawn from a bank located in France
-* accompanied by the transaction number <mark>#@transaction@</mark>, written on the back of the check.
',
	'info_cheque_envoyer_adresse' => 'Thanks to send your check to the following address:',
	'info_cheque_etablir_ordre' => 'Check to the order of "<i>@ordre@</i>"',
	'info_cheque_imprimer' => 'Instructions about your check data will be provided after you click on ’Pay by check’.

Transaction: #@transaction@
_ Amount: @montant@',
	'info_mode_reglement_enregistre' => 'Your choice for your payment mode has been recorded.',
	'info_mode_test' => 'TEST mode (fake payment)',
	'info_prelevement_sepa' => 'Provide your IBAN to our provider @presta@ for activation of debit by SEPA.',
	'info_nb_transactions' => '@nb@ transactions',
	'info_virement' => 'You can pay via bank transfer.
Instructions about your bank transfer will be provided after you click on ’Pay by bank transfer’.

Transaction: #@transaction@
_ Amount: @montant@',
	'info_virement_etablir' => '
Reference for your transfer: #@transaction@
_ Amount: @montant@
_ Bank account:
-* Beneficiary Name: “@ordre@”
-* Beneficiary bank: @banque@<br/>
@adressebanque@
-* IBAN: @iban@
-* BIC: @bic@',

// L
	'label_3ds2_no_preference' => 'No preference for 3DS2 use (default)',
	'label_3ds2_desactive' => '3DS2 Unactive',
	'label_3ds2_souhaite' => '3DS2 Preferred',
	'label_3ds2_requis' => '3DS2 Mandatory',
	'label_3ds2_policy' => 'Payments safety',
	'label_actif' => 'Activate',
	'label_action_append_presta' => 'Add a payment provider',
	'label_carte_AMEX' => 'American Express',
	'label_carte_AMERICAN_EXPRESS' => 'American Express',
	'label_carte_AURORE' => 'Aurore Card',
	'label_carte_BANCONTACT' => 'Bancontact',
	'label_carte_CB' => 'Bank Card',
	'label_carte_CARD' => 'Bank Card',
	'label_carte_DINERS' => 'Diners Card',
	'label_carte_E-CARTEBLEUE' => 'e-Blue Card',
	'label_carte_EUROCARD_MASTERCARD' => 'MasterCard',
	'label_carte_E_CARD' => 'e-Blue Card',
	'label_carte_E_CV' => '<span lang="fr">e-Chèque-Vacances</span>',
	'label_carte_IDEAL' => 'Ideal Card',
	'label_carte_JCB' => 'JCB Card',
	'label_carte_MAESTRO' => 'Maestro Card',
	'label_carte_MASTERCARD' => 'MasterCard',
	'label_carte_ONEY' => 'Oney Card',
	'label_carte_PAYLIB' => 'PayLib',
	'label_carte_SDD' => 'Direct account debit SEPA',
	'label_carte_SEPA_DEBIT' => 'Direct account debit SEPA',
	'label_carte_SOFORT_BANKING' => 'Sofort Banking Card',
	'label_carte_VISA' => 'Visa card',
	'label_carte_VISA_ELECTRON' => 'Visa Electron Card',
	'label_configuration_autres_moyens' => 'Use other payment methods:',
	'label_configuration_cartes' => 'Use payment Cards:',
	'label_configuration_moyen_paiement' => 'Use payment methods:',
	'label_configuration_cheque_adresse' => 'Address where checks should be sent',
	'label_configuration_cheque_notice' => 'Additional displayed information',
	'label_configuration_cheque_ordre' => 'Pay to the order of',
	'label_configuration_nom_compte' => 'Account name',
	'label_configuration_type' => 'Payments type',
	'label_configuration_type_abo' => 'Recurring payments',
	'label_configuration_type_abo_acte' => 'Both',
	'label_configuration_type_acte' => 'Single payments',
	'label_configuration_virement_adresse_banque' => 'Bank Address',
	'label_configuration_virement_banque' => 'Beneficiary bank name',
	'label_configuration_virement_bic' => 'BIC',
	'label_configuration_virement_iban' => 'IBAN',
	'label_configuration_virement_notice' => 'Additional displayed information',
	'label_configuration_virement_ordre' => 'Beneficiary Account Name',
	'label_email_from_ticket_admin' => '<i>from</i> email  for transaction tickets',
	'label_email_reporting' => 'Send a daily report of payments to email',
	'label_email_ticket_admin' => 'Target email for transaction tickets',
	'label_enregistrer_reglement_reference' => 'Reference',
	'label_enregistrer_reglement_montant' => 'Paid amount',
	'label_filtre_statut_' => 'All',
	'label_filtre_statut_ok' => 'OK',
	'label_filtre_statut_commande' => 'Order',
	'label_filtre_statut_attente' => 'Wait',
	'label_filtre_statut_echec' => 'Fail',
	'label_filtre_statut_abandon' => 'Abort',
	'label_filtre_statut_rembourse' => 'Refunded',
	'label_inactif' => 'Disable',
	'label_mode_paiement' => 'Transaction payment modes',
	'label_mode_paiement_abo' => 'Subscriptions payment modes',
	'label_mode_test' => 'Use in TEST mode (no real payment)',
	'label_notifications' => 'Notifications',
	'label_presta_abo_paybox' => 'Paybox <a href="http://www.paybox.com/">http://www.paybox.com/</a>',
	'label_presta_abo_simu' => 'Simulation (requires authorisation by define)',
	'label_presta_cheque' => 'Checks (manual check remittance)',
	'label_presta_cmcic' => 'CMCIC <a href="https://www.cmcicpaiement.fr/fr/">cmcicpaiement.fr</a>',
	'label_presta_cmcic_banque' => 'Bank',
	'label_presta_ogone' => 'Ogone <a href="http://www.ogone.fr/">http://www.ogone.fr/</a>',
	'label_presta_paybox' => 'Paybox <a href="http://www.paybox.com/">http://www.paybox.com/</a>',
	'label_presta_paypal' => 'Paypal (low secure version) <a href="http://www.paypal.fr/">http://www.paypal.fr/</a>',
	'label_presta_paypalexpress' => 'Paypal Express Checkout <a href="http://www.paypal.fr/">http://www.paypal.fr/</a>',
	'label_presta_payzen' => 'PayZen <a href="https://www.payzen.eu/">https://www.payzen.eu/</a>',
	'label_presta_simu' => 'Simulation (requires authorisation by define)',
	'label_presta_sips' => 'SIPS',
	'label_presta_sips_service' => 'Service',
	'label_presta_systempay' => 'Systempay',
	'label_presta_virement' => 'Bank Transfer',
	'label_prestataires' => 'Payment Providers',
	'label_remboursement_raison' => 'Reason of refunding',
	'label_resilier_abonnement' => 'Cancel subscription',
	'label_signature_algo' => 'Sign Method',
	'label_tri_mode' => 'Method',
	'label_tri_autorisation' => 'Authorization',
	'label_tri_montant_ht' => 'Net',
	'label_tri_montant_ttc' => 'Gr.',
	'label_tri_montant_paye' => 'Paid',
	'label_tri_parrain' => 'Affiliate',
	'label_tri_statut' => 'Status',
	'label_type_paiement_cb_generique' => 'Credit Card',
	'label_type_paiement_cheque' => 'Check',
	'label_type_paiement_paypal' => 'Paypal Account',
	'label_type_paiement_sepa' => 'Debit',
	'label_type_paiement_simu' => 'Fake payment',
	'label_type_paiement_virement' => 'Bank Transfer',
	'legend_sips_logo_page_paiement' => 'Logos for payment page',

// P
	'payer' => 'Pay',
	'paiement' => 'Payment',
	'paiement_securise' => 'Secured payment',
	'payer_avec' => 'Pay by <span>@nom@</span>',
	'payer_par_carte' => 'Pay by @carte@',
	'payer_par_carte_bancaire' => 'Pay by credit card',
	'payer_par_moyen_securise' => 'Pay by secured method',
	'payer_par_cheque' => 'Pay by check',
	'payer_par_e_cheque_vacances' => 'Pay by <span lang="fr">e-Chèque-Vacances</span>',
	'payer_par_gratuit' => 'You have nothing to pay',
	'payer_par_prelevement_sepa' => 'Pay by direct account debit (SEPA)',
	'payer_par_virement' => 'Pay by bank transfer',

// T
	'titre_bouton_payer_gratuit' => 'Validate the order',
	'texte_confirmer_suppression_presta' => 'Remove this payment provider?',
	'texte_confirmer_resilier' => 'Cancel this subscription?',
	'titre_menu_configurer' => 'Online payments',
	'titre_menu_transactions' => 'Transactions',
	'titre_mode_paiement_securise' => 'I choose a secure method of payment:',
	'titre_page_configurer_paiement' => 'Online payments',
	'titre_payer_transaction' => 'Pay this transaction',
	'titre_reglement_annule' => 'Cancellation',
	'titre_reglement_ok' => 'Successfull payment',
	'titre_reglement_attente' => 'Payment pending',
	'titre_rien_a_payer' => 'Nothing to pay',
	'titre_transaction' => 'Transaction',
	'titre_transactions' => 'Transactions',
);

