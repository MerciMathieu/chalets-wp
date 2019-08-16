<?php
/**
 * La configuration de base de votre installation WordPress.
 *
 * Ce fichier contient les réglages de configuration suivants : réglages MySQL,
 * préfixe de table, clés secrètes, langue utilisée, et ABSPATH.
 * Vous pouvez en savoir plus à leur sujet en allant sur
 * {@link http://codex.wordpress.org/fr:Modifier_wp-config.php Modifier
 * wp-config.php}. C’est votre hébergeur qui doit vous donner vos
 * codes MySQL.
 *
 * Ce fichier est utilisé par le script de création de wp-config.php pendant
 * le processus d’installation. Vous n’avez pas à utiliser le site web, vous
 * pouvez simplement renommer ce fichier en "wp-config.php" et remplir les
 * valeurs.
 *
 * @package WordPress
 */

// ** Réglages MySQL - Votre hébergeur doit vous fournir ces informations. ** //
/** Nom de la base de données de WordPress. */
define( 'DB_NAME', 'chalets_et_caviar' );

/** Utilisateur de la base de données MySQL. */
define( 'DB_USER', 'root' );

/** Mot de passe de la base de données MySQL. */
define( 'DB_PASSWORD', '' );

/** Adresse de l’hébergement MySQL. */
define( 'DB_HOST', 'localhost' );

/** Jeu de caractères à utiliser par la base de données lors de la création des tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** Type de collation de la base de données.
  * N’y touchez que si vous savez ce que vous faites.
  */
define('DB_COLLATE', '');

/**#@+
 * Clés uniques d’authentification et salage.
 *
 * Remplacez les valeurs par défaut par des phrases uniques !
 * Vous pouvez générer des phrases aléatoires en utilisant
 * {@link https://api.wordpress.org/secret-key/1.1/salt/ le service de clefs secrètes de WordPress.org}.
 * Vous pouvez modifier ces phrases à n’importe quel moment, afin d’invalider tous les cookies existants.
 * Cela forcera également tous les utilisateurs à se reconnecter.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         '0T?1DCnv6H.d,J<]Zr%}[bX-x?VN:/)<S;4{?%S7?})JyR&&:L4fs&?wy_&g+,0/' );
define( 'SECURE_AUTH_KEY',  '#q??GVm7yz;-#.Nrfjjm/K1#43TZRySyL6k<+/N%;I[XQMvm3QW%o4W</1ww__oz' );
define( 'LOGGED_IN_KEY',    '+T &!ust ETcTfa6Dvt(LTILL|YiaVW_RUzw6@A)Pz^wlf|+f_[Beagek2U2!ohI' );
define( 'NONCE_KEY',        'rpzT^R@$1dhE<V|d=hY@y>p:y=aHDaJg*eeuIx q1iMiwJRau|WKVE5H^nzr.a i' );
define( 'AUTH_SALT',        'Uj+,clH:0bv;=|;|:i U2/IDbf8T=J5XvJWB7sktBbeaZ%q6dpdw7 ,2)|8h0,y*' );
define( 'SECURE_AUTH_SALT', 'rc06M*h{?xd{q%*/LW-7rz3Fuby!KTrvVO_Gn|fmA4igRvv&%O}K3:Okp6H%Ab)&' );
define( 'LOGGED_IN_SALT',   '|t*{[J2*tv[K/>N7&mE8%>YXDF?#JX8vc1rM{an;x6O=VP2w{:`ct!}cg^EziiCo' );
define( 'NONCE_SALT',       '`$*{RD`}GykG<Zy7%p.=)!vmurGu]~<k`kY+{dFI>i`vd_q}_ed.tMYK8kfuO#;E' );
/**#@-*/

/**
 * Préfixe de base de données pour les tables de WordPress.
 *
 * Vous pouvez installer plusieurs WordPress sur une seule base de données
 * si vous leur donnez chacune un préfixe unique.
 * N’utilisez que des chiffres, des lettres non-accentuées, et des caractères soulignés !
 */
$table_prefix = 'wp_';

/**
 * Pour les développeurs : le mode déboguage de WordPress.
 *
 * En passant la valeur suivante à "true", vous activez l’affichage des
 * notifications d’erreurs pendant vos essais.
 * Il est fortemment recommandé que les développeurs d’extensions et
 * de thèmes se servent de WP_DEBUG dans leur environnement de
 * développement.
 *
 * Pour plus d’information sur les autres constantes qui peuvent être utilisées
 * pour le déboguage, rendez-vous sur le Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', false);

/* C’est tout, ne touchez pas à ce qui suit ! Bonne publication. */

/** Chemin absolu vers le dossier de WordPress. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Réglage des variables de WordPress et de ses fichiers inclus. */
require_once(ABSPATH . 'wp-settings.php');
