<?php

// Les 3 lignes suivantes sont necessaires dans le htaccess

#RewriteRule ^pos_init$  spip.php?action=bank_response&bankp=internetplus [QSA,L]
#RewriteRule ^bundle-responder/responder$ spip.php?action=bank_autoresponse&bankp=internetplus [QSA,L]
#RewriteRule ^pos_bundle$ spip.php?action=bank_response&bankp=internetplus&abo=oui [QSA,L]
