<?php

// Ceci est un fichier langue de SPIP -- This is a SPIP language file
// Produit automatiquement par le plugin LangOnet à partir de la langue source fr
// Module: bank
// Langue: fr
// Date: 29-05-2015 10:11:27
// Items: 109

if (!defined('_ECRIRE_INC_VERSION')) return;

$GLOBALS[$GLOBALS['idx_lang']] = array(

// A
	'abonnement_avec' => 'Abonnement par <i>@nom@</i>',
	'abonnement_choisissez_cb' => 'Choisissez votre carte bancaire :',
	'abonnement_par_carte_bancaire' => 'Abonnement par carte bancaire :',
	'abonnement_par_cb' => 'avec ma carte bancaire',
	'abonnement_par_prelevement_sepa' => 'Abonnement par prélèvement automatique :',

// B
	'bouton_descendre' => 'descendre',
	'bouton_enregistrer_reglement_carte' => 'Payer par carte',
	'bouton_enregistrer_reglement_cheque' => 'Régler par chèque',
	'bouton_enregistrer_reglement_ok' => 'Le réglement a bien été reçu',
	'bouton_enregistrer_reglement_virement' => 'Régler par virement bancaire',
	'bouton_monter' => 'monter',
	'bouton_rembourser' => 'Marquer comme remboursée',
	'bouton_abondonner_transaction' => 'Abandonner cette transaction',

// C
	'carte_bleu' => 'Carte Bleue',
	'choisissez_cb' => 'Choisissez votre carte bancaire :',
	'confirme_reglement_annule' => 'L\'opération a été annulée. Aucun règlement n\'a été réalisé.',
	'confirme_reglement_pris_en_compte' => 'Votre règlement a bien été pris en compte, et nous vous en remercions.',
	'confirme_reglement_attente' => 'Votre règlement est en attente, nous vous informerons dès sa bonne réception.',

// E
	'erreur_aucun_moyen_paiement' => 'Aucun moyen de paiement n\'est disponible',
	'erreur_confirmer_montant_reglement_different' => 'Confirmer que le règlement ne correspond pas au montant attendu',
	'erreur_transaction_echec' => 'Aucun règlement n\'a été réalisé. (Transaction Ref. @ref@)',
	'erreur_transaction_invalide' => 'Une erreur est survenue, les données reçues ne sont pas conformes.',
	'erreur_transaction_traitement_incomplet' => 'La prise en compte du paiement de cette transaction a été interrompue en cours de traitement. Une réparation manuelle de la base de données est nécessaire.',
	'erreur_serveur_indisponible' => 'Une erreur est survenue ou le service de paiement ne réponds pas.',
	'erreur_ressayer_plus_tard' => 'Ré-essayez dans quelques minutes',
	'explication_enregistrer_reglement_montant' => 'Corriger si ne correspond pas au montant effectivement payé',
	'explication_page_configurer_paiement' => 'Configurez les systèmes de paiement actifs',

// I
	'info_aucune_transaction' => 'Aucune transaction',
	'info_1_transaction' => '1 transaction',
	'info_cheque_envoyer' => 'Envoyez votre chèque libellé en euros
-* à l\'ordre de « <mark>@ordre@</mark> » ;
-* du montant exact <mark>@montant@</mark> ;
-* compensable dans une agence bancaire située en France ;
-* accompagné du numéro de transaction <mark>#@transaction@</mark> à noter au dos du chèque (pas sur la partie détachable).
',
	'info_cheque_envoyer_adresse' => 'Merci d\'envoyer votre chèque à l\'adresse :',
	'info_cheque_etablir_ordre' => 'Chèque établi à l\'ordre de « <i>@ordre@</i> »',
	'info_cheque_imprimer' => 'Les instructions concernant l\'établissement du chèque seront fournies après avoir cliqué sur ’Régler par chèque’.

Transaction : #@transaction@
_ Montant : @montant@',
	'info_mode_reglement_enregistre' => 'Nous avons bien enregistré votre choix de mode de règlement.',
	'info_mode_test' => '[@presta@] Mode TEST (paiement fictif)',
	'info_prelevement_sepa' => 'Saisir votre R.I.B chez notre prestataire @presta@ pour la mise en place du prélèvement par SEPA.',
	'info_nb_transactions' => '@nb@ transactions',
	'info_virement' => 'Vous pouvez payer par virement bancaire.
Les instructions concernant l’établissement du virement seront fournies après avoir cliqué sur ’Régler par virement bancaire’.

Transaction : #@transaction@
_ Montant : @montant@',
	'info_virement_etablir' => '
Libellé de votre virement : #@transaction@
_ Montant : @montant@
_ Compte bancaire :
-* Bénéficiaire : « @ordre@ »
-* Banque : @banque@<br/>
@adressebanque@
-* IBAN : @iban@
-* BIC : @bic@',

// L
	'label_3ds2_no_preference' => 'Pas de préférence pour 3DS2 (défaut)',
	'label_3ds2_desactive' => '3DS2 Désactivé',
	'label_3ds2_souhaite' => '3DS2 Souhaité',
	'label_3ds2_requis' => '3DS2 Obligatoire',
	'label_3ds2_policy' => 'Sécurisation',
	'label_actif' => 'Activer',
	'label_action_append_presta' => 'Ajouter un prestataire',
	'label_carte_AMEX' => 'Carte American Express',
	'label_carte_AMERICAN_EXPRESS' => 'Carte American Express',
	'label_carte_AURORE' => 'Carte Aurore',
	'label_carte_BANCONTACT' => 'Bancontact',
	'label_carte_CB' => 'Carte Bleue',
	'label_carte_CARD' => 'Carte Bancaire',
	'label_carte_DINERS' => 'Carte Diners',
	'label_carte_E-CARTEBLEUE' => 'e-Carte Bleue',
	'label_carte_EUROCARD_MASTERCARD' => 'Carte MasterCard',
	'label_carte_E_CARD' => 'e-Carte Bleue',
	'label_carte_E_CV' => 'e-Chèque-Vacances',
	'label_carte_IDEAL' => 'Carte Ideal',
	'label_carte_JCB' => 'Carte JCB',
	'label_carte_MAESTRO' => 'Carte Maestro',
	'label_carte_MASTERCARD' => 'Carte MasterCard',
	'label_carte_ONEY' => 'Carte Oney',
	'label_carte_PAYLIB' => 'PayLib',
	'label_carte_SDD' => 'Prélèvement SEPA',
	'label_carte_SEPA_DEBIT' => 'Débit SEPA',
	'label_carte_SOFORT_BANKING' => 'Carte Sofort Banking',
	'label_carte_VISA' => 'Carte Visa',
	'label_carte_VISA_ELECTRON' => 'Carte Visa Electron',
	'label_configuration_autres_moyens' => 'Proposer aussi :',
	'label_configuration_cartes' => 'Proposer les cartes de paiement :',
	'label_configuration_moyen_paiement' => 'Proposer les moyens de paiement :',
	'label_configuration_cheque_adresse' => 'Adresse où envoyer les chèques',
	'label_configuration_cheque_devise' => 'Devise',
	'label_configuration_cheque_notice' => 'Information complémentaire affichée',
	'label_configuration_cheque_ordre' => 'Ordre',
	'label_configuration_nom_compte' => 'Nom du compte',
	'label_configuration_type' => 'Type de paiements',
	'label_configuration_type_abo' => 'Paiements Récurrents',
	'label_configuration_type_abo_acte' => 'Les deux',
	'label_configuration_type_acte' => 'Paiements Uniques',
	'label_configuration_virement_adresse_banque' => 'Adresse de la banque',
	'label_configuration_virement_banque' => 'Nom de la banque',
	'label_configuration_virement_bic' => 'BIC',
	'label_configuration_virement_iban' => 'IBAN',
	'label_configuration_virement_notice' => 'Remarques complémentaires :',
	'label_configuration_virement_ordre' => 'Compte bénéficiaire :',
	'label_email_from_ticket_admin' => 'Email <i>from</i> des tickets d\'achat',
	'label_email_reporting' => 'Envoyer un reporting quotidien à l\'adresse mail',
	'label_email_ticket_admin' => 'Email destinataire des tickets d\'achat',
	'label_enregistrer_reglement_reference' => 'Reference',
	'label_enregistrer_reglement_montant' => 'Montant réglé',
	'label_filtre_statut_' => 'Toutes',
	'label_filtre_statut_ok' => 'OK',
	'label_filtre_statut_commande' => 'Cde',
	'label_filtre_statut_attente' => 'Att.',
	'label_filtre_statut_echec' => 'Echec',
	'label_filtre_statut_abandon' => 'Abandon',
	'label_filtre_statut_rembourse' => 'Remb.',
	'label_inactif' => 'Désactiver',
	'label_mode_paiement' => 'Modes de paiements a l\'acte',
	'label_mode_paiement_abo' => 'Modes de paiements des abonnements',
	'label_mode_test' => 'Utiliser en mode TEST (aucun paiement réel)',
	'label_notifications' => 'Notifications',
	'label_presta_abo_paybox' => 'Paybox <a href="http://www.paybox.com/">paybox.com</a>',
	'label_presta_abo_simu' => 'Simulation (necessite une autorisation par define)',
	'label_presta_cheque' => 'Chèques (encaissement manuel)',
	'label_presta_clicandpay' => 'ClicAndPay <a href="https://clicandpay.groupecdn.fr/doc/fr-FR/">clicandpay.groupecdn.fr</a>',
	'label_presta_cmcic' => 'Monetico <a href="https://www.monetico-paiement.fr/">monetico-paiement.fr</a>',
	'label_presta_cmcic_banque' => 'Banque',
	'label_presta_ogone' => 'Ogone <a href="https://ingenico.fr/payments">ingenico.fr</a>',
	'label_presta_paybox' => 'Paybox <a href="http://www.paybox.com/">paybox.com</a>',
	'label_presta_stripe' => 'Stripe <a href="https://stripe.com/fr">stripe.com/fr</a>',
	'label_presta_paypal' => 'Paypal (faiblement sécurisé) <a href="https://www.paypal.fr/">paypal.fr</a>',
	'label_presta_paypalexpress' => 'Paypal Express Checkout <a href="https://www.paypal.fr/">paypal.fr</a>',
	'label_presta_payzen' => 'PayZen <a href="https://www.payzen.eu/">https://www.payzen.eu/</a>',
	'label_presta_simu' => 'Simulation (necessite une autorisation par define)',
	'label_presta_sips' => 'SIPS',
	'label_presta_sipsv2' => 'SIPS v2',
	'label_presta_sips_service' => 'Service',
	'label_presta_systempay' => 'Systempay',
	'label_presta_virement' => 'Virement bancaire',
	'label_prestataires' => 'Prestataires',
	'label_remboursement_raison' => 'Raison du remboursement',
	'label_resilier_abonnement' => 'Résilier',
	'label_signature_algo' => 'Méthode Signature',
	'label_tri_mode' => 'Mode',
	'label_tri_autorisation' => 'Autorisation',
	'label_tri_devise' => 'Devise',
	'label_tri_montant_ht' => 'HT',
	'label_tri_montant_ttc' => 'TTC',
	'label_tri_montant_paye' => 'Réglé',
	'label_tri_parrain' => 'Parrain',
	'label_tri_statut' => 'Statut',
	'label_type_paiement_cb_generique' => 'Carte bancaire',
	'label_type_paiement_cheque' => 'Chèque',
	'label_type_paiement_paypal' => 'Compte Paypal',
	'label_type_paiement_sepa' => 'Prélèvement',
	'label_type_paiement_simu' => 'Paiement fictif',
	'label_type_paiement_virement' => 'Virement',
	'legend_sips_logo_page_paiement' => 'Logos page de paiement',

// P
	'payer' => 'Payer',
	'paiement' => 'Paiement',
	'paiement_securise' => 'Paiement sécurisé',
	'payer_avec' => 'Payer par <span>@nom@</span>',
	'payer_par_carte' => 'Payer par @carte@',
	'payer_par_carte_bancaire' => 'Payer par carte bancaire',
	'payer_par_moyen_securise' => 'Payer par mode de paiement sécurisé',
	'payer_par_cheque' => 'Payer par chèque',
	'payer_par_e_cheque_vacances' => 'Payer par e-Chèque-Vacances',
	'payer_par_gratuit' => 'Vous n\'avez rien à payer',
	'payer_par_prelevement_sepa' => 'Payer par prélèvement automatique',
	'payer_par_virement' => 'Payer par virement bancaire',

// T
	'titre_bouton_payer_gratuit' => 'Valider la commande',
	'texte_confirmer_suppression_presta' => 'Supprimer ce prestataire bancaire ?',
	'texte_confirmer_resilier' => 'Résilier cet abonnement ?',
	'titre_menu_configurer' => 'Paiements en ligne',
	'titre_menu_transactions' => 'Transactions',
	'titre_mode_paiement_securise' => 'Je choisis mon mode de paiement sécurisé :',
	'titre_page_configurer_paiement' => 'Paiements en ligne',
	'titre_payer_transaction' => 'Payer la transaction',
	'titre_reglement_annule' => 'Annulation',
	'titre_reglement_ok' => 'Règlement réussi',
	'titre_reglement_attente' => 'Règlement en attente',
	'titre_rien_a_payer' => 'Rien à payer',
	'titre_transaction' => 'Transaction',
	'titre_transactions' => 'Transactions',
);
