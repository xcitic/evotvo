<?php
/**
 * The base configurations of the WordPress.
 *
 * This file has the following configurations: MySQL settings, Table Prefix,
 * Secret Keys, and ABSPATH. You can find more information by visiting
 * {@link http://codex.wordpress.org/Editing_wp-config.php Editing wp-config.php}
 * Codex page. You can get the MySQL settings from your web host.
 *
 * This file is used by the wp-config.php creation script during the
 * installation. You don't have to use the web site, you can just copy this file
 * to "wp-config.php" and fill in the values.
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'xciticvi_etv');


/** MySQL database username */
define('DB_USER', 'xciticvi_etv');


/** MySQL database password */
define('DB_PASSWORD', 'Sami6493');


/** MySQL hostname */
define('DB_HOST', 'localhost');


/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'kqm$?D%[@3brV,-kStbNs&l?v2:V;dG}teMPiMuVG(^~iqC[oEj{Sd`5i$x)0S`d');

define('SECURE_AUTH_KEY',  '8pGx;f- kLBZiXOz>7h,:UoJ#M`*GhBQy+!(;h5Nib;i>WzoY!MC6Avok^>I21Xv');

define('LOGGED_IN_KEY',    '~+;EG |)xp-TK IG(M0+E0a-9Y|8zqVt;Mx/|nXgX[sWz$sSZ2+v^dRzB?Q0EU|l');

define('NONCE_KEY',        ']|7&nzv0@z]{R+QMPRk>I4iKZ+/$#KN|Xq/*sJ]zs[wOo[UTG5B[1YJ_A9DK>8|m');

define('AUTH_SALT',        'Zl%{}UJ8c@ec6J;]w#3)$yf|p|2=V|:cPU+HZ(|AQn/*RR0qW{R|ry0RVZ2rQ.,;');

define('SECURE_AUTH_SALT', 'jtHlu+h9db3?WeiP}!f3%`J9D$N#T$9s-O(-!Hj*v5|Ma=DR,fTe/;)j#KXu9`59');

define('LOGGED_IN_SALT',   '%vGX4|FVV-:5/N`D6oD]$Gp`r$+Ay`c>Z+ L7|:_whRb%s1A0Rqh3Fjsq);ewe:u');

define('NONCE_SALT',       '5~_OL+FP;iGMG{?6CjS[9kq>(IqvMJiFQObaTsj_24Ns{?L/?ll64+Kk4VR4dMI|');


/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each a unique
 * prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';


/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 */
define('WP_DEBUG', false);

/* Multisite */
define( 'WP_ALLOW_MULTISITE', true );

define('MULTISITE', true);
define('SUBDOMAIN_INSTALL', true);
define('DOMAIN_CURRENT_SITE', 'evotvo.com');
define('PATH_CURRENT_SITE', '/');
define('SITE_ID_CURRENT_SITE', 1);
define('BLOG_ID_CURRENT_SITE', 1);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
