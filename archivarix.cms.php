<?php
/**
 * Archivarix CMS
 *
 * PHP version 5.6 or newer
 * Required extensions: PDO_SQLITE
 * Recommended extensions: curl, dom, fileinfo, iconv, intl, json, libxml, pdo_sqlite, zip
 *
 * LICENSE:
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @package    Archivarix.CMS
 * @version    Release: 0.1.200608
 * @author     Archivarix Team <hello@archivarix.com>
 * @telegram   https://t.me/ArchivarixSupport
 * @messenger  https://m.me/ArchivarixSupport
 * @copyright  2017-2020 Archivarix LLC
 * @license    https://www.gnu.org/licenses/gpl.html GNU GPLv3
 * @link       https://archivarix.com
 */

@ini_set( 'display_errors', 0 );
@ini_set( 'max_execution_time', 600 );
@ini_set( 'memory_limit', '256M' );

/**
 * Set your password to access.
 * Please, do not use simple or short passwords!
 */
const ACMS_PASSWORD = '';

/*
 * Separate password for a safe mode where you cannot create or
 * edit custom files with php code or change password. You can set
 * this password only to always work in a safe mode.
 */
const ACMS_SAFE_PASSWORD = '';

/**
 * Restrict access by setting IPs separated by commas
 * CIDR masks are also allowed.
 * Example: 1.2.3.4, 5.6.7.8/24
 */
const ACMS_ALLOWED_IPS = '';

/*
* This option disables left tree menu to save memory if
* a total number of URLs for a domain is larger than a
* number set. By default, 10 000 files.
*/
const ACMS_URLS_LIMIT = 10000;

/*
* This option limits results output for Search and Replace so
* your browser will not hang on a huge html page. It will not
* limit actual replace process.
*/
const ACMS_MATCHES_LIMIT = 5000;

/*
 * Set to 1 to purge all existing history and disable
 * history/backups to save space.
 */
const ACMS_DISABLE_HISTORY = 0;

/*
 * Tasks that can be performed for a long time will be performed
 * in parts with the intervals specified below.
 */
const ACMS_TIMEOUT = 30;

/*
 * Set a domain if you run a website on a subdomain
 * of the original domain.
 */
const ACMS_CUSTOM_DOMAIN = '';

/*
 * Set only if you renamed your .content.xxxxxxxx to different
 * name or if you have multiple content directories.
 */
const ACMS_CONTENT_PATH = '';

/*
 * Disable features that can potentially be harmful to the website
 * like uploading custom files with php or changing password. Editing
 * the website content is still fully available.
 */
const ACMS_SAFE_MODE = 0;


/**
 * DO NOT EDIT UNDER THIS LINE UNLESS YOU KNOW WHAT YOU ARE DOING
 */
const ACMS_VERSION = '0.1.200608';
define( 'ACMS_START_TIME', microtime( true ) );
session_start();

$ACMS = [
  'ACMS_ALLOWED_IPS'     => ACMS_ALLOWED_IPS,
  'ACMS_CUSTOM_DOMAIN'   => ACMS_CUSTOM_DOMAIN,
  'ACMS_URLS_LIMIT'      => ACMS_URLS_LIMIT,
  'ACMS_MATCHES_LIMIT'   => ACMS_MATCHES_LIMIT,
  'ACMS_DISABLE_HISTORY' => ACMS_DISABLE_HISTORY,
  'ACMS_TIMEOUT'         => ACMS_TIMEOUT,
  'ACMS_SAFE_MODE'       => ACMS_SAFE_MODE,
  'ACMS_PASSWORD'        => '',
  'ACMS_SAFE_PASSWORD'   => '',
];

$sourcePath = getSourceRoot();
loadAcmsSettings();
checkAllowedIp();

if ( isset( $_GET['lang'] ) ) {
  $_SESSION['archivarix.lang'] = $_GET['lang'];
  header( 'Location: ' . parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) );
  http_response_code( 302 );
  exit;
}

if ( empty( $_SESSION['archivarix.lang'] ) ) {
  $_SESSION['archivarix.lang'] = detectLanguage();
}

$GLOBALS['L'] = loadLocalization( $_SESSION['archivarix.lang'] );


$accessAllowed     = checkAccess();
$extensionsAllowed = empty( getMissingExtensions( ['json', 'pdo_sqlite'] ) );


if ( isset( $_GET['expert'] ) ) {
  $_SESSION['archivarix.expert'] = $_GET['expert'];
  header( 'Location: ' . parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) );
  http_response_code( 302 );
  exit;
}

if ( empty ( $_SESSION['acms_xsrf'] ) ) {
  $_SESSION['acms_xsrf'] = getRandomString( 32 );
}

if ( $accessAllowed &&
  !empty( $_POST['action'] ) &&
  $_POST['action'] == 'set.acms.settings' ) {
  setAcmsSettings( $_POST['settings'] );
  addWarning( L( 'Settings were updated.' ), 1, L( 'Settings' ) );
  $section = 'settings';
  $LOADER  = loadLoaderSettings();
  loadAcmsSettings();
  $accessAllowed = checkAccess();
  checkAllowedIp();
}

header( 'X-Robots-Tag: noindex, nofollow' );

if ( isset( $_GET['logout'] ) ) {
  unset( $_SESSION['archivarix.logged'] );
  unset( $_SESSION['archivarix.expert'] );
  unset( $_SESSION['archivarix.safe_mode'] );
  header( 'Location: ' . parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) );
  http_response_code( 302 );
  exit;
}

function addWarning( $message, $level = 1, $title = '', $monospace = false )
{
  global $warnings;
  switch ( $level ) {
    case 1 :
      $color = "success";
      break;
    case 2 :
      $color = "primary";
      break;
    case 3 :
      $color = "warning";
      break;
    case 4 :
      $color = "danger";
      break;
    default :
      $color = "success";
      break;
  }
  if ( is_array( $message ) ) {
    $message = '<pre>' . print_r( $message, 1 ) . '</pre>';
  } elseif ( $monospace ) {
    $message = '<pre>' . $message . '</pre>';
  }
  $warnings[] = array('message' => $message, 'level' => $color, 'title' => $title);
}

function backupFile( $rowid, $action )
{
  global $ACMS;
  if ( $ACMS['ACMS_DISABLE_HISTORY'] ) {
    return;
  }

  global $sourcePath;
  $pdo = newPDO();

  if ( !file_exists( $sourcePath . DIRECTORY_SEPARATOR . 'backup' ) ) {
    mkdir( $sourcePath . DIRECTORY_SEPARATOR . 'backup', 0777, true );
  }

  $metaData = getMetaData( $rowid );

  createTable( 'backup' );

  $filename = sprintf( '%08d.%s.file', $metaData['rowid'], microtime( true ) );
  if ( !empty( $metaData['filename'] ) ) {
    copy( $sourcePath . DIRECTORY_SEPARATOR . $metaData['folder'] . DIRECTORY_SEPARATOR . $metaData['filename'], $sourcePath . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . $filename );
  } else {
    touch( $sourcePath . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . $filename );
  }

  $stmt = $pdo->prepare( "INSERT INTO backup (id, action, settings, filename, created) VALUES (:id, :action, :settings, :filename, :created)" );
  $stmt->execute( [
    'id'       => $metaData['rowid'],
    'action'   => $action,
    'settings' => json_encode( $metaData ),
    'filename' => $filename,
    'created'  => time(),
  ] );
}

function checkAccess()
{
  global $ACMS;

  if ( ACMS_SAFE_MODE || $ACMS['ACMS_SAFE_MODE'] ) {
    $_SESSION['archivarix.safe_mode'] = 1;
  } else {
    $_SESSION['archivarix.safe_mode'] = 0;
  }

  if ( !empty( $_SESSION['archivarix.logged'] ) ) {
    if ( strlen( $ACMS['ACMS_PASSWORD'] ) && password_verify( $ACMS['ACMS_PASSWORD'], $_SESSION['archivarix.logged'] ) ) return true;
    if ( strlen( ACMS_PASSWORD ) && password_verify( ACMS_PASSWORD, $_SESSION['archivarix.logged'] ) ) return true;
    if ( strlen( $ACMS['ACMS_SAFE_PASSWORD'] ) && password_verify( $ACMS['ACMS_SAFE_PASSWORD'], $_SESSION['archivarix.logged'] ) ) {
      $_SESSION['archivarix.safe_mode'] = 1;
      return true;
    }
    if ( strlen( ACMS_SAFE_PASSWORD ) && password_verify( ACMS_SAFE_PASSWORD, $_SESSION['archivarix.logged'] ) ) {
      $_SESSION['archivarix.safe_mode'] = 1;
      return true;
    }

    if ( !strlen( ACMS_PASSWORD ) && !strlen( $ACMS['ACMS_PASSWORD'] ) && !strlen( ACMS_SAFE_PASSWORD ) && !strlen( $ACMS['ACMS_SAFE_PASSWORD'] ) ) {
      unset( $_SESSION['archivarix.logged'] );
      return true;
    }
    unset( $_SESSION['archivarix.logged'] );
    return false;
  }

  if ( isset( $_POST['password'] ) && strlen( $_POST['password'] ) ) {
    if ( strlen( $ACMS['ACMS_PASSWORD'] ) && password_verify( $_POST['password'], $ACMS['ACMS_PASSWORD'] ) ) {
      $_SESSION['archivarix.logged'] = password_hash( $ACMS['ACMS_PASSWORD'], PASSWORD_DEFAULT );
      return true;
    }
    if ( strlen( ACMS_PASSWORD ) && $_POST['password'] == ACMS_PASSWORD ) {
      $_SESSION['archivarix.logged'] = password_hash( ACMS_PASSWORD, PASSWORD_DEFAULT );
      return true;
    }
    if ( strlen( $ACMS['ACMS_SAFE_PASSWORD'] ) && password_verify( $_POST['password'], $ACMS['ACMS_SAFE_PASSWORD'] ) ) {
      $_SESSION['archivarix.logged']    = password_hash( $ACMS['ACMS_SAFE_PASSWORD'], PASSWORD_DEFAULT );
      $_SESSION['archivarix.safe_mode'] = 1;
      return true;
    }
    if ( strlen( ACMS_SAFE_PASSWORD ) && $_POST['password'] == ACMS_SAFE_PASSWORD ) {
      $_SESSION['archivarix.logged']    = password_hash( ACMS_SAFE_PASSWORD, PASSWORD_DEFAULT );
      $_SESSION['archivarix.safe_mode'] = 1;
      return true;
    }
    error_log( "Archivarix CMS login failed; time: " . date( 'c' ) . "; ip: " . $_SERVER['REMOTE_ADDR'] );
    return false;
  }

  if (
    strlen( ACMS_PASSWORD ) ||
    strlen( $ACMS['ACMS_PASSWORD'] ) ||
    strlen( ACMS_SAFE_PASSWORD ) ||
    strlen( $ACMS['ACMS_SAFE_PASSWORD'] )
  ) return false;

  return true;
}

function checkAllowedIp()
{
  global $ACMS;

  if ( empty( $ACMS['ACMS_ALLOWED_IPS'] ) ) return true;
  $ipsCleaned = preg_replace( '~[^\d./,:]~', '', $ACMS['ACMS_ALLOWED_IPS'] );
  $ipsArray   = explode( ',', $ipsCleaned );

  foreach ( $ipsArray as $cidr ) {
    if ( matchCidr( $_SERVER['REMOTE_ADDR'], $cidr ) ) {
      return true;
    }
  }

  http_response_code( 404 );
  exit;
}

function checkIntegrationPrerequisite()
{
  $file = __DIR__ . DIRECTORY_SEPARATOR . 'index.php';
  if ( !file_exists( $file ) ) return;
  if ( preg_match( '~@package[\s]+WordPress~', file_get_contents( $file ) ) ) return 'WordPress';
  if ( preg_match( '~@package[\s]+Joomla.Site~', file_get_contents( $file ) ) ) return 'Joomla';
}

function inSafeMode()
{
  if ( $_SESSION['archivarix.safe_mode'] == 0 ) return false;
  if ( $_SESSION['archivarix.safe_mode'] == 1 ) return true;
  return true;
}

function checkSourceStructure()
{
  global $sourcePath;
  if ( !strlen( $sourcePath ) || $sourcePath == __DIR__ . DIRECTORY_SEPARATOR . '.content.tmp' ) return false;
  $ignoreFiles   = ['.acms.settings.json', '.loader.settings.json', '1px.png', 'empty.css', 'empty.ico', 'empty.js', 'robots.txt', 'structure.db', 'structure.db-shm', 'structure.db-wal', 'structure.json', 'structure.legacy.db',];
  $ignoreFolders = ['binary', 'html', 'backup', 'imports', 'exports', 'includes', 'templates'];
  $allowed       = array_merge( $ignoreFiles, $ignoreFolders, ['.', '..'] );
  $filesList     = scandir( $sourcePath );
  $extraFiles    = [];

  foreach ( $filesList as $filename ) {
    if ( in_array( $filename, $allowed ) ) continue;
    $extraFiles[] = $filename;
  }

  if ( empty( $extraFiles ) ) return false;

  addWarning( L( 'Attention! Your .content.xxxxxx directory contains extra files that do not belong there!' ) . '<br>' . L( 'The latest Loader version includes files from .content.xxxxxx/includes/ directory.' ) . '<br>' . sprintf( L( 'Extra files or directories found: %s' ), implode( ', ', $extraFiles ) ), 3, L( 'System check' ) );

  return true;
}

function checkXsrf()
{
  if ( !empty( $_POST ) && ( empty( $_POST['xsrf'] ) || $_POST['xsrf'] !== $_SESSION['acms_xsrf'] ) ) {
    addWarning( L( 'Security token mismatch. The action was not performed. Your session probably expired.' ), 4, L( 'Request check' ) );
    return;
  }
  return true;
}

function cleanTemplate( $content )
{
  $content = preg_replace( "~<img [^>]*\{\{@FILE\('[-a-z0-9]+'\)\}\}[^>]*>~is", "", $content );
  $content = preg_replace( "~\{\{@(STRING|HTML|URL|FILE)\('([-a-z0-9]+)'\)\}\}~is", "", $content );
  return $content;
}

function clearMissingUrls()
{
  $pdo = newPDO();
  if ( $pdo->exec( "DROP TABLE missing" ) ) return true;
}

function cloneUrl( $rowid, $cloneUrlPath )
{
  global $sourcePath;
  $metaData = getMetaData( $rowid );

  if ( $metaData['request_uri'] == encodePath( $cloneUrlPath ) ) {
    return false;
  }

  $pdo  = newPDO();
  $stmt = $pdo->prepare( 'INSERT INTO structure (url,protocol,hostname,request_uri,folder,filename,mimetype,charset,filesize,filetime,url_original,enabled,redirect) VALUES (:url,:protocol,:hostname,:request_uri,:folder,:filename,:mimetype,:charset,:filesize,:filetime,:url_original,:enabled,:redirect)' );
  $stmt->execute( [
    'url'          => $metaData['protocol'] . '://' . $metaData['hostname'] . encodePath( $cloneUrlPath ),
    'protocol'     => $metaData['protocol'],
    'hostname'     => $metaData['hostname'],
    'request_uri'  => encodePath( $cloneUrlPath ),
    'folder'       => $metaData['folder'],
    'filename'     => '',
    'mimetype'     => $metaData['mimetype'],
    'charset'      => $metaData['charset'],
    'filesize'     => filesize( $sourcePath . DIRECTORY_SEPARATOR . $metaData['folder'] . DIRECTORY_SEPARATOR . $metaData['filename'] ),
    'filetime'     => date( 'YmdHis' ),
    'url_original' => '',
    'enabled'      => $metaData['enabled'],
    'redirect'     => $metaData['redirect'],
  ] );

  $cloneID = $pdo->lastInsertId();
  if ( $cloneID ) {
    $cloneFileExtension = pathinfo( $metaData['filename'], PATHINFO_EXTENSION );
    copy( $sourcePath . DIRECTORY_SEPARATOR . $metaData['folder'] . DIRECTORY_SEPARATOR . $metaData['filename'], $sourcePath . DIRECTORY_SEPARATOR . $metaData['folder'] . DIRECTORY_SEPARATOR . sprintf( '%s.%08d.%s', convertPathToFilename( $cloneUrlPath ), $cloneID, $cloneFileExtension ) );
    $stmt = $pdo->prepare( 'UPDATE structure SET filename = :filename WHERE rowid = :rowid' );
    $stmt->execute( [
      'filename' => sprintf( '%s.%08d.%s', convertPathToFilename( $cloneUrlPath ), $cloneID, $cloneFileExtension ),
      'rowid'    => $cloneID,
    ] );
    backupFile( $cloneID, 'create' );
    return $cloneID;
  }
}

function convertDomain( $domain )
{
  global $ACMS;

  if ( $ACMS['ACMS_CUSTOM_DOMAIN'] ) {
    $domain = preg_replace( '~' . preg_quote( ACMS_ORIGINAL_DOMAIN, '~' ) . '~', $ACMS['ACMS_CUSTOM_DOMAIN'], $domain, 1 );
  }

  if ( !$ACMS['ACMS_CUSTOM_DOMAIN'] && substr( $_SERVER['HTTP_HOST'], -strlen( ACMS_ORIGINAL_DOMAIN ) ) !== ACMS_ORIGINAL_DOMAIN ) {
    $domain = $_SERVER['HTTP_HOST'];
  }

  return $domain;
}

function convertEncoding( $content, $to, $from )
{
  if ( strtolower( $to ) == strtolower( $from ) ) {
    return $content;
  }

  $supported_charsets = ['437', '500', '500V1', '850', '851', '852', '855', '856', '857', '860', '861', '862', '863', '864', '865', '866', '866NAV', '869', '874', '904', '1026', '1046', '1047', '8859_1', '8859_2', '8859_3', '8859_4', '8859_5', '8859_6', '8859_7', '8859_8', '8859_9', '10646-1:1993', '10646-1:1993/UCS4', 'ANSI_X3.4-1968', 'ANSI_X3.4-1986', 'ANSI_X3.4', 'ANSI_X3.110-1983', 'ANSI_X3.110', 'ARABIC', 'ARABIC7', 'ARMSCII-8', 'ASCII', 'ASMO-708', 'ASMO_449', 'BALTIC', 'BIG-5', 'BIG-FIVE', 'BIG5-HKSCS', 'BIG5', 'BIG5HKSCS', 'BIGFIVE', 'BRF', 'BS_4730', 'CA', 'CN-BIG5', 'CN-GB', 'CN', 'CP-AR', 'CP-GR', 'CP-HU', 'CP037', 'CP038', 'CP273', 'CP274', 'CP275', 'CP278', 'CP280', 'CP281', 'CP282', 'CP284', 'CP285', 'CP290', 'CP297', 'CP367', 'CP420', 'CP423', 'CP424', 'CP437', 'CP500', 'CP737', 'CP770', 'CP771', 'CP772', 'CP773', 'CP774', 'CP775', 'CP803', 'CP813', 'CP819', 'CP850', 'CP851', 'CP852', 'CP855', 'CP856', 'CP857', 'CP860', 'CP861', 'CP862', 'CP863', 'CP864', 'CP865', 'CP866', 'CP866NAV', 'CP868', 'CP869', 'CP870', 'CP871', 'CP874', 'CP875', 'CP880', 'CP891', 'CP901', 'CP902', 'CP903', 'CP904', 'CP905', 'CP912', 'CP915', 'CP916', 'CP918', 'CP920', 'CP921', 'CP922', 'CP930', 'CP932', 'CP933', 'CP935', 'CP936', 'CP937', 'CP939', 'CP949', 'CP950', 'CP1004', 'CP1008', 'CP1025', 'CP1026', 'CP1046', 'CP1047', 'CP1070', 'CP1079', 'CP1081', 'CP1084', 'CP1089', 'CP1097', 'CP1112', 'CP1122', 'CP1123', 'CP1124', 'CP1125', 'CP1129', 'CP1130', 'CP1132', 'CP1133', 'CP1137', 'CP1140', 'CP1141', 'CP1142', 'CP1143', 'CP1144', 'CP1145', 'CP1146', 'CP1147', 'CP1148', 'CP1149', 'CP1153', 'CP1154', 'CP1155', 'CP1156', 'CP1157', 'CP1158', 'CP1160', 'CP1161', 'CP1162', 'CP1163', 'CP1164', 'CP1166', 'CP1167', 'CP1250', 'CP1251', 'CP1252', 'CP1253', 'CP1254', 'CP1255', 'CP1256', 'CP1257', 'CP1258', 'CP1282', 'CP1361', 'CP1364', 'CP1371', 'CP1388', 'CP1390', 'CP1399', 'CP4517', 'CP4899', 'CP4909', 'CP4971', 'CP5347', 'CP9030', 'CP9066', 'CP9448', 'CP10007', 'CP12712', 'CP16804', 'CPIBM861', 'CSA7-1', 'CSA7-2', 'CSASCII', 'CSA_T500-1983', 'CSA_T500', 'CSA_Z243.4-1985-1', 'CSA_Z243.4-1985-2', 'CSA_Z243.419851', 'CSA_Z243.419852', 'CSDECMCS', 'CSEBCDICATDE', 'CSEBCDICATDEA', 'CSEBCDICCAFR', 'CSEBCDICDKNO', 'CSEBCDICDKNOA', 'CSEBCDICES', 'CSEBCDICESA', 'CSEBCDICESS', 'CSEBCDICFISE', 'CSEBCDICFISEA', 'CSEBCDICFR', 'CSEBCDICIT', 'CSEBCDICPT', 'CSEBCDICUK', 'CSEBCDICUS', 'CSEUCKR', 'CSEUCPKDFMTJAPANESE', 'CSGB2312', 'CSHPROMAN8', 'CSIBM037', 'CSIBM038', 'CSIBM273', 'CSIBM274', 'CSIBM275', 'CSIBM277', 'CSIBM278', 'CSIBM280', 'CSIBM281', 'CSIBM284', 'CSIBM285', 'CSIBM290', 'CSIBM297', 'CSIBM420', 'CSIBM423', 'CSIBM424', 'CSIBM500', 'CSIBM803', 'CSIBM851', 'CSIBM855', 'CSIBM856', 'CSIBM857', 'CSIBM860', 'CSIBM863', 'CSIBM864', 'CSIBM865', 'CSIBM866', 'CSIBM868', 'CSIBM869', 'CSIBM870', 'CSIBM871', 'CSIBM880', 'CSIBM891', 'CSIBM901', 'CSIBM902', 'CSIBM903', 'CSIBM904', 'CSIBM905', 'CSIBM918', 'CSIBM921', 'CSIBM922', 'CSIBM930', 'CSIBM932', 'CSIBM933', 'CSIBM935', 'CSIBM937', 'CSIBM939', 'CSIBM943', 'CSIBM1008', 'CSIBM1025', 'CSIBM1026', 'CSIBM1097', 'CSIBM1112', 'CSIBM1122', 'CSIBM1123', 'CSIBM1124', 'CSIBM1129', 'CSIBM1130', 'CSIBM1132', 'CSIBM1133', 'CSIBM1137', 'CSIBM1140', 'CSIBM1141', 'CSIBM1142', 'CSIBM1143', 'CSIBM1144', 'CSIBM1145', 'CSIBM1146', 'CSIBM1147', 'CSIBM1148', 'CSIBM1149', 'CSIBM1153', 'CSIBM1154', 'CSIBM1155', 'CSIBM1156', 'CSIBM1157', 'CSIBM1158', 'CSIBM1160', 'CSIBM1161', 'CSIBM1163', 'CSIBM1164', 'CSIBM1166', 'CSIBM1167', 'CSIBM1364', 'CSIBM1371', 'CSIBM1388', 'CSIBM1390', 'CSIBM1399', 'CSIBM4517', 'CSIBM4899', 'CSIBM4909', 'CSIBM4971', 'CSIBM5347', 'CSIBM9030', 'CSIBM9066', 'CSIBM9448', 'CSIBM12712', 'CSIBM16804', 'CSIBM11621162', 'CSISO4UNITEDKINGDOM', 'CSISO10SWEDISH', 'CSISO11SWEDISHFORNAMES', 'CSISO14JISC6220RO', 'CSISO15ITALIAN', 'CSISO16PORTUGESE', 'CSISO17SPANISH', 'CSISO18GREEK7OLD', 'CSISO19LATINGREEK', 'CSISO21GERMAN', 'CSISO25FRENCH', 'CSISO27LATINGREEK1', 'CSISO49INIS', 'CSISO50INIS8', 'CSISO51INISCYRILLIC', 'CSISO58GB1988', 'CSISO60DANISHNORWEGIAN', 'CSISO60NORWEGIAN1', 'CSISO61NORWEGIAN2', 'CSISO69FRENCH', 'CSISO84PORTUGUESE2', 'CSISO85SPANISH2', 'CSISO86HUNGARIAN', 'CSISO88GREEK7', 'CSISO89ASMO449', 'CSISO90', 'CSISO92JISC62991984B', 'CSISO99NAPLPS', 'CSISO103T618BIT', 'CSISO111ECMACYRILLIC', 'CSISO121CANADIAN1', 'CSISO122CANADIAN2', 'CSISO139CSN369103', 'CSISO141JUSIB1002', 'CSISO143IECP271', 'CSISO150', 'CSISO150GREEKCCITT', 'CSISO151CUBA', 'CSISO153GOST1976874', 'CSISO646DANISH', 'CSISO2022CN', 'CSISO2022JP', 'CSISO2022JP2', 'CSISO2022KR', 'CSISO2033', 'CSISO5427CYRILLIC', 'CSISO5427CYRILLIC1981', 'CSISO5428GREEK', 'CSISO10367BOX', 'CSISOLATIN1', 'CSISOLATIN2', 'CSISOLATIN3', 'CSISOLATIN4', 'CSISOLATIN5', 'CSISOLATIN6', 'CSISOLATINARABIC', 'CSISOLATINCYRILLIC', 'CSISOLATINGREEK', 'CSISOLATINHEBREW', 'CSKOI8R', 'CSKSC5636', 'CSMACINTOSH', 'CSNATSDANO', 'CSNATSSEFI', 'CSN_369103', 'CSPC8CODEPAGE437', 'CSPC775BALTIC', 'CSPC850MULTILINGUAL', 'CSPC862LATINHEBREW', 'CSPCP852', 'CSSHIFTJIS', 'CSUCS4', 'CSUNICODE', 'CSWINDOWS31J', 'CUBA', 'CWI-2', 'CWI', 'CYRILLIC', 'DE', 'DEC-MCS', 'DEC', 'DECMCS', 'DIN_66003', 'DK', 'DS2089', 'DS_2089', 'E13B', 'EBCDIC-AT-DE-A', 'EBCDIC-AT-DE', 'EBCDIC-BE', 'EBCDIC-BR', 'EBCDIC-CA-FR', 'EBCDIC-CP-AR1', 'EBCDIC-CP-AR2', 'EBCDIC-CP-BE', 'EBCDIC-CP-CA', 'EBCDIC-CP-CH', 'EBCDIC-CP-DK', 'EBCDIC-CP-ES', 'EBCDIC-CP-FI', 'EBCDIC-CP-FR', 'EBCDIC-CP-GB', 'EBCDIC-CP-GR', 'EBCDIC-CP-HE', 'EBCDIC-CP-IS', 'EBCDIC-CP-IT', 'EBCDIC-CP-NL', 'EBCDIC-CP-NO', 'EBCDIC-CP-ROECE', 'EBCDIC-CP-SE', 'EBCDIC-CP-TR', 'EBCDIC-CP-US', 'EBCDIC-CP-WT', 'EBCDIC-CP-YU', 'EBCDIC-CYRILLIC', 'EBCDIC-DK-NO-A', 'EBCDIC-DK-NO', 'EBCDIC-ES-A', 'EBCDIC-ES-S', 'EBCDIC-ES', 'EBCDIC-FI-SE-A', 'EBCDIC-FI-SE', 'EBCDIC-FR', 'EBCDIC-GREEK', 'EBCDIC-INT', 'EBCDIC-INT1', 'EBCDIC-IS-FRISS', 'EBCDIC-IT', 'EBCDIC-JP-E', 'EBCDIC-JP-KANA', 'EBCDIC-PT', 'EBCDIC-UK', 'EBCDIC-US', 'EBCDICATDE', 'EBCDICATDEA', 'EBCDICCAFR', 'EBCDICDKNO', 'EBCDICDKNOA', 'EBCDICES', 'EBCDICESA', 'EBCDICESS', 'EBCDICFISE', 'EBCDICFISEA', 'EBCDICFR', 'EBCDICISFRISS', 'EBCDICIT', 'EBCDICPT', 'EBCDICUK', 'EBCDICUS', 'ECMA-114', 'ECMA-118', 'ECMA-128', 'ECMA-CYRILLIC', 'ECMACYRILLIC', 'ELOT_928', 'ES', 'ES2', 'EUC-CN', 'EUC-JISX0213', 'EUC-JP-MS', 'EUC-JP', 'EUC-KR', 'EUC-TW', 'EUCCN', 'EUCJP-MS', 'EUCJP-OPEN', 'EUCJP-WIN', 'EUCJP', 'EUCKR', 'EUCTW', 'FI', 'FR', 'GB', 'GB2312', 'GB13000', 'GB18030', 'GBK', 'GB_1988-80', 'GB_198880', 'GEORGIAN-ACADEMY', 'GEORGIAN-PS', 'GOST_19768-74', 'GOST_19768', 'GOST_1976874', 'GREEK-CCITT', 'GREEK', 'GREEK7-OLD', 'GREEK7', 'GREEK7OLD', 'GREEK8', 'GREEKCCITT', 'HEBREW', 'HP-GREEK8', 'HP-ROMAN8', 'HP-ROMAN9', 'HP-THAI8', 'HP-TURKISH8', 'HPGREEK8', 'HPROMAN8', 'HPROMAN9', 'HPTHAI8', 'HPTURKISH8', 'HU', 'IBM-803', 'IBM-856', 'IBM-901', 'IBM-902', 'IBM-921', 'IBM-922', 'IBM-930', 'IBM-932', 'IBM-933', 'IBM-935', 'IBM-937', 'IBM-939', 'IBM-943', 'IBM-1008', 'IBM-1025', 'IBM-1046', 'IBM-1047', 'IBM-1097', 'IBM-1112', 'IBM-1122', 'IBM-1123', 'IBM-1124', 'IBM-1129', 'IBM-1130', 'IBM-1132', 'IBM-1133', 'IBM-1137', 'IBM-1140', 'IBM-1141', 'IBM-1142', 'IBM-1143', 'IBM-1144', 'IBM-1145', 'IBM-1146', 'IBM-1147', 'IBM-1148', 'IBM-1149', 'IBM-1153', 'IBM-1154', 'IBM-1155', 'IBM-1156', 'IBM-1157', 'IBM-1158', 'IBM-1160', 'IBM-1161', 'IBM-1162', 'IBM-1163', 'IBM-1164', 'IBM-1166', 'IBM-1167', 'IBM-1364', 'IBM-1371', 'IBM-1388', 'IBM-1390', 'IBM-1399', 'IBM-4517', 'IBM-4899', 'IBM-4909', 'IBM-4971', 'IBM-5347', 'IBM-9030', 'IBM-9066', 'IBM-9448', 'IBM-12712', 'IBM-16804', 'IBM037', 'IBM038', 'IBM256', 'IBM273', 'IBM274', 'IBM275', 'IBM277', 'IBM278', 'IBM280', 'IBM281', 'IBM284', 'IBM285', 'IBM290', 'IBM297', 'IBM367', 'IBM420', 'IBM423', 'IBM424', 'IBM437', 'IBM500', 'IBM775', 'IBM803', 'IBM813', 'IBM819', 'IBM848', 'IBM850', 'IBM851', 'IBM852', 'IBM855', 'IBM856', 'IBM857', 'IBM860', 'IBM861', 'IBM862', 'IBM863', 'IBM864', 'IBM865', 'IBM866', 'IBM866NAV', 'IBM868', 'IBM869', 'IBM870', 'IBM871', 'IBM874', 'IBM875', 'IBM880', 'IBM891', 'IBM901', 'IBM902', 'IBM903', 'IBM904', 'IBM905', 'IBM912', 'IBM915', 'IBM916', 'IBM918', 'IBM920', 'IBM921', 'IBM922', 'IBM930', 'IBM932', 'IBM933', 'IBM935', 'IBM937', 'IBM939', 'IBM943', 'IBM1004', 'IBM1008', 'IBM1025', 'IBM1026', 'IBM1046', 'IBM1047', 'IBM1089', 'IBM1097', 'IBM1112', 'IBM1122', 'IBM1123', 'IBM1124', 'IBM1129', 'IBM1130', 'IBM1132', 'IBM1133', 'IBM1137', 'IBM1140', 'IBM1141', 'IBM1142', 'IBM1143', 'IBM1144', 'IBM1145', 'IBM1146', 'IBM1147', 'IBM1148', 'IBM1149', 'IBM1153', 'IBM1154', 'IBM1155', 'IBM1156', 'IBM1157', 'IBM1158', 'IBM1160', 'IBM1161', 'IBM1162', 'IBM1163', 'IBM1164', 'IBM1166', 'IBM1167', 'IBM1364', 'IBM1371', 'IBM1388', 'IBM1390', 'IBM1399', 'IBM4517', 'IBM4899', 'IBM4909', 'IBM4971', 'IBM5347', 'IBM9030', 'IBM9066', 'IBM9448', 'IBM12712', 'IBM16804', 'IEC_P27-1', 'IEC_P271', 'INIS-8', 'INIS-CYRILLIC', 'INIS', 'INIS8', 'INISCYRILLIC', 'ISIRI-3342', 'ISIRI3342', 'ISO-2022-CN-EXT', 'ISO-2022-CN', 'ISO-2022-JP-2', 'ISO-2022-JP-3', 'ISO-2022-JP', 'ISO-2022-KR', 'ISO-8859-1', 'ISO-8859-2', 'ISO-8859-3', 'ISO-8859-4', 'ISO-8859-5', 'ISO-8859-6', 'ISO-8859-7', 'ISO-8859-8', 'ISO-8859-9', 'ISO-8859-9E', 'ISO-8859-10', 'ISO-8859-11', 'ISO-8859-13', 'ISO-8859-14', 'ISO-8859-15', 'ISO-8859-16', 'ISO-10646', 'ISO-10646/UCS2', 'ISO-10646/UCS4', 'ISO-10646/UTF-8', 'ISO-10646/UTF8', 'ISO-CELTIC', 'ISO-IR-4', 'ISO-IR-6', 'ISO-IR-8-1', 'ISO-IR-9-1', 'ISO-IR-10', 'ISO-IR-11', 'ISO-IR-14', 'ISO-IR-15', 'ISO-IR-16', 'ISO-IR-17', 'ISO-IR-18', 'ISO-IR-19', 'ISO-IR-21', 'ISO-IR-25', 'ISO-IR-27', 'ISO-IR-37', 'ISO-IR-49', 'ISO-IR-50', 'ISO-IR-51', 'ISO-IR-54', 'ISO-IR-55', 'ISO-IR-57', 'ISO-IR-60', 'ISO-IR-61', 'ISO-IR-69', 'ISO-IR-84', 'ISO-IR-85', 'ISO-IR-86', 'ISO-IR-88', 'ISO-IR-89', 'ISO-IR-90', 'ISO-IR-92', 'ISO-IR-98', 'ISO-IR-99', 'ISO-IR-100', 'ISO-IR-101', 'ISO-IR-103', 'ISO-IR-109', 'ISO-IR-110', 'ISO-IR-111', 'ISO-IR-121', 'ISO-IR-122', 'ISO-IR-126', 'ISO-IR-127', 'ISO-IR-138', 'ISO-IR-139', 'ISO-IR-141', 'ISO-IR-143', 'ISO-IR-144', 'ISO-IR-148', 'ISO-IR-150', 'ISO-IR-151', 'ISO-IR-153', 'ISO-IR-155', 'ISO-IR-156', 'ISO-IR-157', 'ISO-IR-166', 'ISO-IR-179', 'ISO-IR-193', 'ISO-IR-197', 'ISO-IR-199', 'ISO-IR-203', 'ISO-IR-209', 'ISO-IR-226', 'ISO/TR_11548-1', 'ISO646-CA', 'ISO646-CA2', 'ISO646-CN', 'ISO646-CU', 'ISO646-DE', 'ISO646-DK', 'ISO646-ES', 'ISO646-ES2', 'ISO646-FI', 'ISO646-FR', 'ISO646-FR1', 'ISO646-GB', 'ISO646-HU', 'ISO646-IT', 'ISO646-JP-OCR-B', 'ISO646-JP', 'ISO646-KR', 'ISO646-NO', 'ISO646-NO2', 'ISO646-PT', 'ISO646-PT2', 'ISO646-SE', 'ISO646-SE2', 'ISO646-US', 'ISO646-YU', 'ISO2022CN', 'ISO2022CNEXT', 'ISO2022JP', 'ISO2022JP2', 'ISO2022KR', 'ISO6937', 'ISO8859-1', 'ISO8859-2', 'ISO8859-3', 'ISO8859-4', 'ISO8859-5', 'ISO8859-6', 'ISO8859-7', 'ISO8859-8', 'ISO8859-9', 'ISO8859-9E', 'ISO8859-10', 'ISO8859-11', 'ISO8859-13', 'ISO8859-14', 'ISO8859-15', 'ISO8859-16', 'ISO11548-1', 'ISO88591', 'ISO88592', 'ISO88593', 'ISO88594', 'ISO88595', 'ISO88596', 'ISO88597', 'ISO88598', 'ISO88599', 'ISO88599E', 'ISO885910', 'ISO885911', 'ISO885913', 'ISO885914', 'ISO885915', 'ISO885916', 'ISO_646.IRV:1991', 'ISO_2033-1983', 'ISO_2033', 'ISO_5427-EXT', 'ISO_5427', 'ISO_5427:1981', 'ISO_5427EXT', 'ISO_5428', 'ISO_5428:1980', 'ISO_6937-2', 'ISO_6937-2:1983', 'ISO_6937', 'ISO_6937:1992', 'ISO_8859-1', 'ISO_8859-1:1987', 'ISO_8859-2', 'ISO_8859-2:1987', 'ISO_8859-3', 'ISO_8859-3:1988', 'ISO_8859-4', 'ISO_8859-4:1988', 'ISO_8859-5', 'ISO_8859-5:1988', 'ISO_8859-6', 'ISO_8859-6:1987', 'ISO_8859-7', 'ISO_8859-7:1987', 'ISO_8859-7:2003', 'ISO_8859-8', 'ISO_8859-8:1988', 'ISO_8859-9', 'ISO_8859-9:1989', 'ISO_8859-9E', 'ISO_8859-10', 'ISO_8859-10:1992', 'ISO_8859-14', 'ISO_8859-14:1998', 'ISO_8859-15', 'ISO_8859-15:1998', 'ISO_8859-16', 'ISO_8859-16:2001', 'ISO_9036', 'ISO_10367-BOX', 'ISO_10367BOX', 'ISO_11548-1', 'ISO_69372', 'IT', 'JIS_C6220-1969-RO', 'JIS_C6229-1984-B', 'JIS_C62201969RO', 'JIS_C62291984B', 'JOHAB', 'JP-OCR-B', 'JP', 'JS', 'JUS_I.B1.002', 'KOI-7', 'KOI-8', 'KOI8-R', 'KOI8-RU', 'KOI8-T', 'KOI8-U', 'KOI8', 'KOI8R', 'KOI8U', 'KSC5636', 'L1', 'L2', 'L3', 'L4', 'L5', 'L6', 'L7', 'L8', 'L10', 'LATIN-9', 'LATIN-GREEK-1', 'LATIN-GREEK', 'LATIN1', 'LATIN2', 'LATIN3', 'LATIN4', 'LATIN5', 'LATIN6', 'LATIN7', 'LATIN8', 'LATIN9', 'LATIN10', 'LATINGREEK', 'LATINGREEK1', 'MAC-CENTRALEUROPE', 'MAC-CYRILLIC', 'MAC-IS', 'MAC-SAMI', 'MAC-UK', 'MAC', 'MACCYRILLIC', 'MACINTOSH', 'MACIS', 'MACUK', 'MACUKRAINIAN', 'MIK', 'MS-ANSI', 'MS-ARAB', 'MS-CYRL', 'MS-EE', 'MS-GREEK', 'MS-HEBR', 'MS-MAC-CYRILLIC', 'MS-TURK', 'MS932', 'MS936', 'MSCP949', 'MSCP1361', 'MSMACCYRILLIC', 'MSZ_7795.3', 'MS_KANJI', 'NAPLPS', 'NATS-DANO', 'NATS-SEFI', 'NATSDANO', 'NATSSEFI', 'NC_NC0010', 'NC_NC00-10', 'NC_NC00-10:81', 'NF_Z_62-010', 'NF_Z_62-010_(1973)', 'NF_Z_62-010_1973', 'NF_Z_62010', 'NF_Z_62010_1973', 'NO', 'NO2', 'NS_4551-1', 'NS_4551-2', 'NS_45511', 'NS_45512', 'OS2LATIN1', 'OSF00010001', 'OSF00010002', 'OSF00010003', 'OSF00010004', 'OSF00010005', 'OSF00010006', 'OSF00010007', 'OSF00010008', 'OSF00010009', 'OSF0001000A', 'OSF00010020', 'OSF00010100', 'OSF00010101', 'OSF00010102', 'OSF00010104', 'OSF00010105', 'OSF00010106', 'OSF00030010', 'OSF0004000A', 'OSF0005000A', 'OSF05010001', 'OSF100201A4', 'OSF100201A8', 'OSF100201B5', 'OSF100201F4', 'OSF100203B5', 'OSF1002011C', 'OSF1002011D', 'OSF1002035D', 'OSF1002035E', 'OSF1002035F', 'OSF1002036B', 'OSF1002037B', 'OSF10010001', 'OSF10010004', 'OSF10010006', 'OSF10020025', 'OSF10020111', 'OSF10020115', 'OSF10020116', 'OSF10020118', 'OSF10020122', 'OSF10020129', 'OSF10020352', 'OSF10020354', 'OSF10020357', 'OSF10020359', 'OSF10020360', 'OSF10020364', 'OSF10020365', 'OSF10020366', 'OSF10020367', 'OSF10020370', 'OSF10020387', 'OSF10020388', 'OSF10020396', 'OSF10020402', 'OSF10020417', 'PT', 'PT2', 'PT154', 'R8', 'R9', 'RK1048', 'ROMAN8', 'ROMAN9', 'RUSCII', 'SE', 'SE2', 'SEN_850200_B', 'SEN_850200_C', 'SHIFT-JIS', 'SHIFT_JIS', 'SHIFT_JISX0213', 'SJIS-OPEN', 'SJIS-WIN', 'SJIS', 'SS636127', 'STRK1048-2002', 'ST_SEV_358-88', 'T.61-8BIT', 'T.61', 'T.618BIT', 'TCVN-5712', 'TCVN', 'TCVN5712-1', 'TCVN5712-1:1993', 'THAI8', 'TIS-620', 'TIS620-0', 'TIS620.2529-1', 'TIS620.2533-0', 'TIS620', 'TS-5881', 'TSCII', 'TURKISH8', 'UCS-2', 'UCS-2BE', 'UCS-2LE', 'UCS-4', 'UCS-4BE', 'UCS-4LE', 'UCS2', 'UCS4', 'UHC', 'UJIS', 'UK', 'UNICODE', 'UNICODEBIG', 'UNICODELITTLE', 'US-ASCII', 'US', 'UTF-7', 'UTF-8', 'UTF-16', 'UTF-16BE', 'UTF-16LE', 'UTF-32', 'UTF-32BE', 'UTF-32LE', 'UTF7', 'UTF8', 'UTF16', 'UTF16BE', 'UTF16LE', 'UTF32', 'UTF32BE', 'UTF32LE', 'VISCII', 'WCHAR_T', 'WIN-SAMI-2', 'WINBALTRIM', 'WINDOWS-31J', 'WINDOWS-874', 'WINDOWS-936', 'WINDOWS-1250', 'WINDOWS-1251', 'WINDOWS-1252', 'WINDOWS-1253', 'WINDOWS-1254', 'WINDOWS-1255', 'WINDOWS-1256', 'WINDOWS-1257', 'WINDOWS-1258', 'WINSAMI2', 'WS2', 'YU'];

  if ( empty( $to ) ) {
    $to = 'utf-8';
  }

  if ( empty( $from ) ) {
    $from = 'utf-8';
  }

  if ( function_exists( 'mb_convert_encoding' ) && count( preg_grep( '~^' . preg_quote( $to, '~' ) . '$~i', mb_list_encodings() ) ) && count( preg_grep( '~^' . preg_quote( $from, '~' ) . '$~i', mb_list_encodings() ) ) ) {
    return mb_convert_encoding( $content, $to, $from );
  }

  if ( function_exists( 'iconv' ) && count( preg_grep( '~^' . preg_quote( $to, '~' ) . '$~i', $supported_charsets ) ) && count( preg_grep( '~^' . preg_quote( $from, '~' ) . '$~i', $supported_charsets ) ) ) {
    return iconv( $from . '//IGNORE', $to . '//IGNORE', $content );
  }

  return $content;
}

function convertHtmlEncoding( $html, $to, $from )
{
  $html = convertEncoding( $html, $to, $from );
  $html = preg_replace( '~<meta[\s]+charset=[^>]+>~is', '<meta charset="' . $to . '">', $html );
  $html = preg_replace( '~<meta[\s]+[^>]*\bhttp-equiv\b[^>]+content-type[^>]+>~is', '<meta http-equiv="content-type" content="text/html; charset=' . $to . '">', $html );
  return $html;
}

function convertIdnToAscii( $string )
{
  if ( function_exists( 'idn_to_ascii' ) && defined( 'INTL_IDNA_VARIANT_UTS46' ) ) {
    return idn_to_ascii( $string, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46 );
  }
  if ( function_exists( 'idn_to_ascii' ) ) {
    return idn_to_ascii( $string, IDNA_DEFAULT );
  }
  return $string;
}

function convertIdnToUtf8( $string )
{
  if ( function_exists( 'idn_to_utf8' ) && defined( 'INTL_IDNA_VARIANT_UTS46' ) ) {
    return idn_to_utf8( $string, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46 );
  }
  if ( function_exists( 'idn_to_utf8' ) ) {
    return idn_to_utf8( $string, IDNA_DEFAULT );
  }
  return $string;
}

function convertPathToFilename( $path, $limit = 130 )
{
  $search  = array('?', '/', ' ', '\'', '\\', ':', '/', '*', '"', '<', '>', '|');
  $replace = array(';', '!', '+', '', '', '', '', '', '', '', '', '');
  if ( $limit ) {
    if ( function_exists( 'mb_substr' ) ) {
      return mb_substr( str_replace( $search, $replace, urldecode( $path ) ), 0, 130 );
    }
    return substr( str_replace( $search, $replace, urldecode( $path ) ), 0, 130 );
  }
  return str_replace( $search, $replace, urldecode( $path ) );
}

function convertUTF8( $taskOffset = 0 )
{
  global $taskIncomplete;
  global $taskIncompleteOffset;
  global $taskStats;
  global $sourcePath;
  global $ACMS;

  if ( !empty( unserialize( $taskStats ) ) ) {
    $stats = unserialize( $taskStats );
  } else {
    $stats = ['pages' => 0, 'processed' => 0, 'total' => 0];
  }

  $mimeTypeSql = "'text/html', 'text/css', 'application/javascript', 'application/x-javascript', 'text/javascript', 'text/plain', 'application/json', 'application/xml', 'text/xml'";

  $pdo  = newPDO();
  $pdo2 = newPDO();
  if ( empty( $stats['total'] ) ) $stats['total'] = $pdo->query( "SELECT COUNT(1) FROM structure WHERE mimetype IN ({$mimeTypeSql}) AND charset != '' AND charset != 'utf-8'" )->fetchColumn();
  $stmt = $pdo->prepare( "SELECT rowid, * FROM structure WHERE mimetype IN ({$mimeTypeSql}) AND charset != '' AND charset != 'utf-8' AND rowid > :taskOffset ORDER BY rowid" );
  $stmt->execute( ['taskOffset' => $taskOffset] );

  while ( $url = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
    backupFile( $url['rowid'], 'convert' );
    $file = $sourcePath . DIRECTORY_SEPARATOR . $url['folder'] . DIRECTORY_SEPARATOR . $url['filename'];
    $html = convertEncoding( file_get_contents( $file ), 'utf-8', $url['charset'] );
    $html = preg_replace( '~<meta[\s]+charset=[^>]+>~is', '<meta charset="utf-8">', $html );
    $html = preg_replace( '~<meta[\s]+[^>]*\bhttp-equiv\b[^>]+content-type[^>]+>~is', '<meta http-equiv="content-type" content="text/html; charset=utf-8">', $html );
    file_put_contents( $file, $html );
    updateFilesize( $url['rowid'], filesize( $file ) );
    $pdo2->exec( "UPDATE structure SET charset = 'utf-8' WHERE rowid = {$url['rowid']}" );
    $stats['pages']++;
    $stats['processed']++;

    if ( $ACMS['ACMS_TIMEOUT'] && ( microtime( true ) - ACMS_START_TIME ) > ( $ACMS['ACMS_TIMEOUT'] - 1 ) ) {
      $taskStats            = serialize( $stats );
      $taskIncomplete       = true;
      $taskIncompleteOffset = $url['rowid'];
      return $stats;
    }
  }

  if ( $stats['processed'] ) createBackupBreakpoint( L( 'Website conversion to UTF-8' ) . '. ' . sprintf( L( 'Processed: %s' ), number_format( $stats['processed'], 0 ) ) );
  return $stats['pages'];
}

function copyRecursive( $source, $destination )
{
  $directory = opendir( $source );
  if ( !file_exists( $destination ) ) mkdir( $destination, 0777, true );
  while ( false !== ( $file = readdir( $directory ) ) ) {
    if ( !in_array( $file, ['.', '..'] ) ) {
      if ( is_dir( $source . DIRECTORY_SEPARATOR . $file ) ) {
        copyRecursive( $source . DIRECTORY_SEPARATOR . $file, $destination . DIRECTORY_SEPARATOR . $file );
      } else {
        copy( $source . DIRECTORY_SEPARATOR . $file, $destination . DIRECTORY_SEPARATOR . $file );
      }
    }
  }
  closedir( $directory );
}

function copyUrl( $metaDataNew )
{
  global $sourcePath;
  global $uuidSettings;

  $mimeNew                 = getMimeInfo( $metaDataNew['mimetype'] );
  $metaDataNew['protocol'] = !empty( $uuidSettings['https'] ) ? 'https' : 'http';
  $metaDataNew['folder']   = $mimeNew['folder'];

  $pdo  = newPDO();
  $stmt = $pdo->prepare( 'INSERT INTO structure (url,protocol,hostname,request_uri,folder,filename,mimetype,charset,filesize,filetime,url_original,enabled,redirect) VALUES (:url,:protocol,:hostname,:request_uri,:folder,:filename,:mimetype,:charset,:filesize,:filetime,:url_original,:enabled,:redirect)' );
  $stmt->execute( [
    'url'          => $metaDataNew['protocol'] . '://' . $metaDataNew['hostname'] . $metaDataNew['request_uri'],
    'protocol'     => $metaDataNew['protocol'],
    'hostname'     => $metaDataNew['hostname'],
    'request_uri'  => $metaDataNew['request_uri'],
    'folder'       => $metaDataNew['folder'],
    'filename'     => '',
    'mimetype'     => $metaDataNew['mimetype'],
    'charset'      => $metaDataNew['charset'],
    'filesize'     => $metaDataNew['filesize'],
    'filetime'     => $metaDataNew['filetime'],
    'url_original' => $metaDataNew['url_original'],
    'enabled'      => $metaDataNew['enabled'],
    'redirect'     => $metaDataNew['redirect'],
  ] );

  $newId                   = $pdo->lastInsertId();
  $metaDataNew['filename'] = sprintf( '%s.%08d.%s', convertPathToFilename( $metaDataNew['request_uri'] ), $newId, $mimeNew['extension'] );
  $stmt                    = $pdo->prepare( "UPDATE structure SET filename = :filename WHERE rowid = :rowid" );
  $stmt->execute( ['filename' => $metaDataNew['filename'], 'rowid' => $newId] );

  copy( $metaDataNew['tmp_file_path'], $sourcePath . DIRECTORY_SEPARATOR . $metaDataNew['folder'] . DIRECTORY_SEPARATOR . $metaDataNew['filename'] );

  backupFile( $newId, 'create' );
}

function createBackupBreakpoint( $name )
{
  createTable( 'backup' );
  $pdo  = newPDO();
  $stmt = $pdo->prepare( "INSERT INTO backup (id, action, settings, created) VALUES (0, 'breakpoint', :settings, :created)" );
  $stmt->execute( [
    'settings' => json_encode( ['name' => $name], JSON_UNESCAPED_UNICODE ),
    'created'  => time(),
  ] );
}

function createCustomFile( $input )
{
  global $sourcePath;
  if ( inSafeMode() && preg_match( '~[<]([?%]|[^>]*script\b[^>]*\blanguage\b[^>]*\bphp\b)~is', $input['content'] ) ) {
    addWarning( L( 'You cannot create or edit custom files with a php code under a safe mode.' ), 4, L( 'Custom Files' ) );
    return false;
  }
  createDirectory( 'includes' );
  $includesPath = $sourcePath . DIRECTORY_SEPARATOR . 'includes';
  $filename     = basename( $input['filename'] );
  if ( !preg_match( '~^[-.\w]+$~i', $filename ) || in_array( $filename, ['.', '..'] ) ) $filename = date( 'Ymd_His' ) . '.txt';
  $file = $includesPath . DIRECTORY_SEPARATOR . $filename;
  file_put_contents( $file, $input['content'] );
  return true;
}

function createDirectory( $directoryName )
{
  global $sourcePath;
  $directoryPath = $sourcePath . DIRECTORY_SEPARATOR . $directoryName;
  if ( !file_exists( $directoryPath ) ) {
    mkdir( $directoryPath, 0777, true );
  }
  return $directoryPath;
}

function createRobotsTxt( $params = [] )
{
  global $uuidSettings;
  global $sourcePath;
  $stats = ['processed' => 0];
  if ( !empty( $params['sitemap_include'] ) && !empty( $params['sitemap'] ) ) {
    $sitemap = parse_url( $params['sitemap'] );
    if ( !empty( $sitemap['path'] ) ) {
      $LOADER                            = loadLoaderSettings();
      $LOADER['ARCHIVARIX_SITEMAP_PATH'] = $sitemap['path'];
      setLoaderSettings( $LOADER );
    }
  }
  $pdo  = newPDO();
  $stmt = $pdo->prepare( 'SELECT DISTINCT hostname FROM structure' );
  $stmt->execute();

  while ( $hostname = $stmt->fetchColumn() ) {
    $robotsTxt = "User-agent: *\nDisallow:";
    if ( !empty( $sitemap['path'] ) ) {
      $subdomain = preg_replace( '~' . preg_quote( $uuidSettings['domain'] ) . '$~', '', $hostname );
      $robotsTxt .= "\n\nSitemap: " . $sitemap['scheme'] . '://' . $subdomain . $sitemap['host'] . $sitemap['path'];
    }

    $rowid = urlExists( ( empty( $uuidSettings['https'] ) ? 'http' : 'https' ) . '://' . $hostname . '/robots.txt' );
    if ( $rowid ) {
      $metaData = getMetaData( $rowid );
      backupFile( $rowid, 'edit' );
      file_put_contents( $sourcePath . DIRECTORY_SEPARATOR . $metaData['folder'] . DIRECTORY_SEPARATOR . $metaData['filename'], $robotsTxt );
      $metaData['urlID']    = $rowid;
      $metaData['mimetype'] = 'text/plain';
      $metaData['charset']  = 'utf-8';
      $metaData['redirect'] = '';
      updateUrlSettings( $metaData );
      updateFilesize( $rowid, strlen( $robotsTxt ) );
    } else {
      $rowid    = createUrl( [
        'hostname' => $hostname,
        'path'     => '/robots.txt',
        'folder'   => 'html',
        'mime'     => 'text/plain',
        'charset'  => 'utf-8',
      ] );
      $metaData = getMetaData( $rowid );
      file_put_contents( $sourcePath . DIRECTORY_SEPARATOR . $metaData['folder'] . DIRECTORY_SEPARATOR . $metaData['filename'], $robotsTxt );
      updateFilesize( $rowid, strlen( $robotsTxt ) );
    }
    $stats['processed']++;
  }
  createBackupBreakpoint( L( 'Allow website in robots.txt' ) . '. ' . sprintf( L( 'Processed: %s' ), number_format( $stats['processed'], 0 ) ) );
  return;
}

function createStructure( $info )
{
  $contentFolder = __DIR__ . DIRECTORY_SEPARATOR . '.content.' . getRandomString( 8 );
  $newDbFile     = $contentFolder . DIRECTORY_SEPARATOR . 'structure.db';
  mkdir( $contentFolder, 0777, true );
  mkdir( $contentFolder . DIRECTORY_SEPARATOR . 'imports', 0777, true );
  mkdir( $contentFolder . DIRECTORY_SEPARATOR . 'html', 0777, true );
  mkdir( $contentFolder . DIRECTORY_SEPARATOR . 'binary', 0777, true );
  touch( $contentFolder . DIRECTORY_SEPARATOR . 'empty.css' );
  touch( $contentFolder . DIRECTORY_SEPARATOR . 'empty.js' );
  touch( $contentFolder . DIRECTORY_SEPARATOR . 'empty.js' );
  file_put_contents( $contentFolder . DIRECTORY_SEPARATOR . '1px.png', base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQAAAAA3bvkkAAAAAnRSTlMAAHaTzTgAAAAKSURBVAjXY2AAAAACAAHiIbwzAAAAAElFTkSuQmCC' ) );
  file_put_contents( $contentFolder . DIRECTORY_SEPARATOR . 'empty.ico', base64_decode( 'AAABAAEAEBACAAEAAQCwAAAAFgAAACgAAAAQAAAAIAAAAAEAAQAAAAAAQAAAAAAAAAAAAAAAAgAAAAIAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAD//wAA//8AAP//AAD//wAA//8AAP//AAD//wAA//8AAP//AAD//wAA//8AAP//AAD//wAA//8AAP//AAD//wAA' ) );
  $newDb = new PDO( "sqlite:{$newDbFile}" );
  $newDb->exec( "PRAGMA journal_mode=WAL" );
  createTable( 'structure', $newDb );
  createTable( 'settings', $newDb );
  foreach ( $info['info']['settings'] as $param => $value ) {
    $stmt = $newDb->prepare( "INSERT INTO settings VALUES(:param, :value)" );
    $stmt->execute( ['param' => $param, 'value' => $value] );
  }
  return $contentFolder;
}

function createTable( $tableName, $pdo = null )
{
  if ( $pdo == null ) $pdo = newPDO();

  switch ( $tableName ) :
    case 'backup' :
      $pdo->exec( "CREATE TABLE IF NOT EXISTS backup (id INTEGER, action TEXT, settings TEXT, filename TEXT, created INTEGER)" );
      break;
    case 'templates' :
      $pdo->exec( "CREATE TABLE IF NOT EXISTS templates (name TEXT PRIMARY KEY, hostname TEXT, mimetype TEXT, charset TEXT, uploads TEXT, path TEXT)" );
      break;
    case 'meta' :
      $pdo->exec( "CREATE TABLE IF NOT EXISTS meta (name TEXT PRIMARY KEY, data TEXT)" );
      break;
    case 'structure' :
      $pdo->exec( "CREATE TABLE IF NOT EXISTS structure (url TEXT, protocol TEXT, hostname TEXT, request_uri TEXT, folder TEXT, filename TEXT, mimetype TEXT, charset TEXT, filesize INTEGER, filetime INTEGER, url_original TEXT, enabled INTEGER DEFAULT 1, redirect TEXT)" );
      $pdo->exec( "CREATE UNIQUE INDEX IF NOT EXISTS url_index ON structure (url)" );
      $pdo->exec( "CREATE INDEX IF NOT EXISTS hostname_index ON structure (hostname)" );
      $pdo->exec( "CREATE INDEX IF NOT EXISTS mimetype_index ON structure (mimetype)" );
      $pdo->exec( "CREATE INDEX IF NOT EXISTS request_uri_index ON structure (request_uri);" );
      break;
    case 'settings' :
      $pdo->exec( "CREATE TABLE IF NOT EXISTS settings (param TEXT,value TEXT)" );
      break;
    case 'missing' :
      $pdo->exec( 'CREATE TABLE IF NOT EXISTS missing (url TEXT PRIMARY KEY, status INTEGER DEFAULT 0, ignore INTEGER DEFAULT 0)' );
      break;
  endswitch;
}

function createTemplateRecord( $template )
{
  $pdo  = newPDO();
  $stmt = $pdo->prepare( "INSERT INTO templates (name, hostname, mimetype, charset, uploads, path) VALUES (:name, :hostname, :mimetype, :charset, :uploads, :path)" );
  $stmt->bindParam( 'name', $template['name'], PDO::PARAM_STR );
  $stmt->bindParam( 'hostname', $template['hostname'], PDO::PARAM_STR );
  $stmt->bindParam( 'mimetype', $template['mimetype'], PDO::PARAM_STR );
  $stmt->bindParam( 'charset', $template['charset'], PDO::PARAM_STR );
  $stmt->bindParam( 'uploads', $template['uploads'], PDO::PARAM_STR );
  $stmt->bindParam( 'path', $template['path'], PDO::PARAM_STR );
  $stmt->execute();
}

function createTemplateFromPage( $name, $rowid )
{
  if ( !preg_match( '~^[-a-z0-9_]+$~i', $name ) ) return false;
  global $sourcePath;
  $name = getTemplateNameAvailable( $name );
  $url  = getMetaData( $rowid );
  createDirectory( 'templates' );
  $pdo  = newPDO();
  $stmt = $pdo->prepare( "INSERT INTO templates (name, hostname, mimetype, charset, uploads, path) VALUES (:name, :hostname, :mimetype, :charset, '/uploads/%md5%', '/page-path')" );
  $stmt->bindParam( 'name', $name, PDO::PARAM_STR );
  $stmt->bindParam( 'hostname', $url['hostname'], PDO::PARAM_STR );
  $stmt->bindParam( 'mimetype', $url['mimetype'], PDO::PARAM_STR );
  $stmt->bindParam( 'charset', $url['charset'], PDO::PARAM_STR );
  $stmt->execute();
  copy( $sourcePath . DIRECTORY_SEPARATOR . $url['folder'] . DIRECTORY_SEPARATOR . $url['filename'], $sourcePath . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . $name . '.html' );
  return $name;
}

function createTemplatePage( $input )
{
  global $sourcePath;
  $files        = [];
  $template     = getTemplate( $input['name'] );
  $templateInfo = getTemplateInfo( $input['name'] );

  if ( !empty( $_FILES['params']['tmp_name'] ) ) {
    foreach ( $_FILES['params']['tmp_name'] as $key => $fileTmpName ) {
      if ( empty( $fileTmpName ) ) continue;
      $fileMime      = getMimeInfo( $_FILES['params']['type'][$key] );
      $fileExtension = !empty( pathinfo( $_FILES['params']['name'][$key], PATHINFO_EXTENSION ) ) ? strtolower( pathinfo( $_FILES['params']['name'][$key], PATHINFO_EXTENSION ) ) : $fileMime['extension'];
      $filePath      = $template['uploads'];
      $filePath      = preg_replace( '~%md5%~', md5_file( $fileTmpName ), $filePath );
      $filePath      = preg_replace( '~%filename%~', basename( $_FILES['params']['name'][$key] ), $filePath );
      $filePath      = preg_replace( '~%ext%~', $fileExtension, $filePath );
      $filePath      = preg_replace( '~%year%~', date( 'Y' ), $filePath );
      $filePath      = preg_replace( '~%month%~', date( 'm' ), $filePath );
      $filePath      = preg_replace( '~%day%~', date( 'd' ), $filePath );
      $filePath      = preg_replace( '~%hour%~', date( 'H' ), $filePath );
      $filePath      = preg_replace( '~%minute%~', date( 'i' ), $filePath );
      $filePath      = preg_replace( '~%second%~', date( 's' ), $filePath );
      foreach ( $input['params'] as $paramName => $paramValue ) {
        $filePath = preg_replace( '~%' . preg_quote( $paramName, '~' ) . '%~', sanitizeString( $paramValue, 200, 1, '-' ), $filePath );
      }
      $filePath        = preg_replace( '~%[\w]+%~', '', $filePath );
      $filePath        = preg_replace( '~[/]{2,}~', '/', $filePath );
      $filePath        = getPathAvailable( $filePath );
      $url['hostname'] = $template['hostname'];
      $url['path']     = $filePath;
      $url['mime']     = $_FILES['params']['type'][$key];
      $url['charset']  = ( $fileMime['folder'] == 'html' ? 'utf-8' : '' );
      $rowid           = createUrl( $url );
      $url             = getMetaData( $rowid );
      $fileName        = $sourcePath . DIRECTORY_SEPARATOR . $url['folder'] . DIRECTORY_SEPARATOR . $url['filename'];
      move_uploaded_file( $fileTmpName, $fileName );
      updateFilesize( $rowid, filesize( $fileName ) );
      $files[$key] = $url['request_uri'];
    }
  }

  foreach ( $input['params'] as $paramName => $paramValue ) {
    if ( isset( $templateInfo['params'][$paramName] ) ) {
      foreach ( $templateInfo['params'][$paramName] as $paramInfo ) {
        $template['content'] = preg_replace( '~' . preg_quote( $paramInfo['string'], '~i' ) . '~', convertEncoding( $paramValue, $template['charset'], 'utf-8' ), $template['content'] );
      }
    }
  }

  // files
  foreach ( $files as $fileKey => $filePath ) {
    $template['content'] = preg_replace( "~\{\{@FILE\('" . $fileKey . "'\)\}\}~is", $filePath, $template['content'] );
  }

  $url['hostname'] = $template['hostname'];
  $url['mime']     = $template['mimetype'];
  $url['charset']  = $template['charset'];

  // path
  $url['path'] = $input['path'];
  $url['path'] = preg_replace( '~%year%~', date( 'Y' ), $url['path'] );
  $url['path'] = preg_replace( '~%month%~', date( 'm' ), $url['path'] );
  $url['path'] = preg_replace( '~%day%~', date( 'd' ), $url['path'] );
  $url['path'] = preg_replace( '~%hour%~', date( 'H' ), $url['path'] );
  $url['path'] = preg_replace( '~%minute%~', date( 'i' ), $url['path'] );
  $url['path'] = preg_replace( '~%second%~', date( 's' ), $url['path'] );
  $url['path'] = preg_replace( '~[/]{2,}~', '/', $url['path'] );
  foreach ( $input['params'] as $paramName => $paramValue ) {
    $url['path'] = preg_replace( '~%' . preg_quote( $paramName, '~' ) . '%~', sanitizeString( $paramValue, 200, $input['path_latin'], '-' ), $url['path'] );
  }
  $url['path'] = preg_replace( '~%[\w]+%~', '', $url['path'] );
  $url['path'] = preg_replace( '~[/]{2,}~', '/', $url['path'] );
  $url['path'] = getPathAvailable( $url['path'] );

  // additional built-in replaces
  $template['content'] = preg_replace( "~\{\{@URL\('path'\)\}\}~is", $url['path'], $template['content'] );
  $template['content'] = preg_replace( "~\{\{@URL\('hostname'\)\}\}~is", $template['hostname'], $template['content'] );

  $template['content'] = cleanTemplate( $template['content'] );

  $rowid = createUrl( $url );
  if ( $rowid ) {
    $url  = getMetaData( $rowid );
    $file = $sourcePath . DIRECTORY_SEPARATOR . $url['folder'] . DIRECTORY_SEPARATOR . $url['filename'];
    file_put_contents( $file, $template['content'] );
    updateFilesize( $rowid, filesize( $file ) );
    return $rowid;
  }
}

function createUrl( $input )
{
  if ( pathExists( $input['hostname'], $input['path'] ) ) {
    addWarning( L( 'You cannot create a URL with a path that already exists.' ), 4, L( 'Create new URL' ) );
    return;
  }

  global $uuidSettings;
  global $sourcePath;

  $protocol = ( !empty( $uuidSettings['https'] ) ? 'https' : 'http' );
  $mime     = getMimeInfo( $input['mime'] );
  $pdo      = newPDO();
  $stmt     = $pdo->prepare( "INSERT INTO structure (url,protocol,hostname,request_uri,folder,filename,mimetype,charset,filesize,filetime,url_original,enabled,redirect) VALUES (:url,:protocol,:hostname,:request_uri,:folder,:filename,:mimetype,:charset,:filesize,:filetime,:url_original,:enabled,:redirect)" );
  $stmt->execute( [
    'url'          => $protocol . '://' . $input['hostname'] . encodePath( $input['path'] ),
    'protocol'     => $protocol,
    'hostname'     => $input['hostname'],
    'request_uri'  => encodePath( $input['path'] ),
    'folder'       => $mime['folder'],
    'filename'     => '',
    'mimetype'     => $input['mime'],
    'charset'      => $input['charset'],
    'filesize'     => 0,
    'filetime'     => date( 'YmdHis' ),
    'url_original' => '',
    'enabled'      => 1,
    'redirect'     => '',
  ] );

  $createID = $pdo->lastInsertId();
  if ( $createID ) {
    $file = $sourcePath . DIRECTORY_SEPARATOR . $mime['folder'] . DIRECTORY_SEPARATOR . sprintf( '%s.%08d.%s', convertPathToFilename( $input['path'] ), $createID, $mime['extension'] );
    if ( !empty( $_FILES['create_file']['tmp_name'] ) ) {
      move_uploaded_file( $_FILES['create_file']['tmp_name'], $file );
    } else {
      touch( $file );
    }
    $stmt = $pdo->prepare( 'UPDATE structure SET filename = :filename, filesize = :filesize WHERE rowid = :rowid' );
    $stmt->execute( [
      'filename' => sprintf( '%s.%08d.%s', convertPathToFilename( $input['path'] ), $createID, $mime['extension'] ),
      'filesize' => filesize( $file ),
      'rowid'    => $createID,
    ] );
    backupFile( $createID, 'create' );
    return $createID;
  }
}

function dataLogo()
{
  return "data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA0MDMgNzYiPjxzdHlsZT4uc3Qwe2VuYWJsZS1iYWNrZ3JvdW5kOm5ld30uc3Qxe2ZpbGwtcnVsZTpldmVub2RkO2NsaXAtcnVsZTpldmVub2RkO2ZpbGw6I2ZmYTcwMH0uc3Qye2ZpbGw6I2ZmZn08L3N0eWxlPjxnIGlkPSJFbGxpcHNlXzFfMV8iIGNsYXNzPSJzdDAiPjxjaXJjbGUgY2xhc3M9InN0MSIgY3g9IjM4LjgiIGN5PSIzOCIgcj0iMzcuNiIgaWQ9IkVsbGlwc2VfMV8zXyIvPjwvZz48cGF0aCBjbGFzcz0ic3QyIiBkPSJNMjMuNCAxOS4xYzEuOS0uOCAzLjctMS4yIDUuNC0xLjIgMS40IDAgMi45LjUgNC41IDEuNi44LjYgMS44IDEuNyAyLjggMy40LjcgMS4yIDEuNiAzLjMgMi42IDYuM2w1LjMgMTVjMS4yIDMuNCAyLjUgNiAzLjcgOCAxLjMgMiAyLjQgMy41IDMuNCA0LjVzMi4xIDEuNyAzLjMgMi4xYzEuMS40IDIuMS42IDIuOC42czEuNC0uMSAyLS4ydi40Yy0xLjQuNS0yLjcuNy00LjEuNy0xLjMgMC0yLjctLjMtNC0xLTEuMy0uNy0yLjYtMS42LTMuNy0yLjhDNDUgNTQgNDIuOSA1MC4xIDQxLjIgNDVsLTEuNy00LjlIMjcuNmwtMyA3LjdjLS4xLjMtLjIuNy0uMiAxIDAgLjMuMi43LjUgMS4xcy44LjYgMS40LjZoLjN2LjRoLTguN3YtLjRoLjRjLjcgMCAxLjQtLjIgMi0uNi43LS40IDEuMi0xIDEuNi0xLjlsMTAuOC0yNS44Yy0xLjYtMi4yLTMuNS0zLjMtNS44LTMuMy0xIDAtMi4yLjItMy4zLjdsLS4yLS41em00LjcgMTkuNmgxMWwtMy40LTEwLjFjLS43LTEuOS0xLjMtMy41LTEuOC00LjZsLTUuOCAxNC43eiIgaWQ9IkEiLz48ZyBpZD0iQXJjaGl2YXJpeCI+PHBhdGggY2xhc3M9InN0MiIgZD0iTTk1LjIgMTQuMmMyLjItLjkgNC4zLTEuMyA2LjMtMS4zIDEuNyAwIDMuNC42IDUuMSAxLjggMSAuNyAyIDIgMy4zIDMuOS44IDEuMyAxLjggMy44IDMgNy4zbDYuMSAxNy4zYzEuNCAzLjkgMi44IDYuOSA0LjMgOS4yIDEuNSAyLjMgMi44IDQgNCA1LjJzMi41IDIgMy44IDIuNCAyLjQuNyAzLjIuNyAxLjYtLjEgMi4zLS4ydi41Yy0xLjYuNS0zLjIuOC00LjcuOHMtMy4xLS40LTQuNi0xLjJjLTEuNS0uOC0zLTEuOS00LjMtMy4zLTIuOS0yLjktNS4zLTcuMy03LjMtMTMuM2wtMS45LTUuN0gxMDBsLTMuNSA4LjljLS4yLjQtLjMuOC0uMyAxLjIgMCAuNC4yLjguNSAxLjNzLjkuNyAxLjYuN2guNHYuNWgtOS45di0uNWguNWMuOCAwIDEuNi0uMiAyLjQtLjcuOC0uNSAxLjQtMS4yIDEuOS0yLjJMMTA2IDE3LjhjLTEuOC0yLjYtNC0zLjktNi43LTMuOS0xLjIgMC0yLjUuMy0zLjkuOGwtLjItLjV6bTUuNCAyMi43aDEyLjdsLTQtMTEuN2MtLjgtMi4yLTEuNS00LTIuMS01LjNsLTYuNiAxN3oiLz48cGF0aCBjbGFzcz0ic3QyIiBkPSJNMTM4LjIgMTcuNUgxMzV2MjkuOGMwIC45LjMgMS42LjkgMi4yLjYuNiAxLjQuOSAyLjMuOWguNmwuMS41aC0xMXYtLjVoLjZjLjkgMCAxLjYtLjMgMi4yLS45LjYtLjYuOS0xLjMgMS0yLjJWMTkuNmMwLS45LS40LTEuNi0xLTIuMi0uNi0uNi0xLjQtLjktMi4yLS45aC0uNlYxNmgxMy41YzMgMCA1LjQuOCA3LjEgMi41IDEuNyAxLjcgMi42IDMuOSAyLjYgNi42IDAgMi43LS44IDUtMi41IDYuOS0xLjYgMi0zLjcgMi45LTYgMi45LjUuMiAxLjEuNyAxLjggMS40czEuMyAxLjQgMS44IDIuMWMyLjkgNC4xIDQuNyA2LjYgNS42IDcuNi45LjkgMS41IDEuNiAxLjggMS45LjQuNC44LjcgMS4yIDEgLjQuMy45LjYgMS4zLjggMSAuNSAyIC43IDMuMS43di41aC0yLjhjLTEuNCAwLTIuOC0uMy00LS44LTEuMi0uNS0yLjItMS0yLjgtMS42LS42LS41LTEuMS0xLjEtMS42LTEuNi0uNC0uNS0xLjctMi4yLTMuNy01LjItMi0yLjktMy4yLTQuNi0zLjUtNS0uMy0uNC0uNy0uOC0xLTEuMi0xLjEtMS4xLTIuMS0xLjctMy4yLTEuN3YtLjVjLjMgMCAuNi4xIDEgLjFzMSAwIDEuNi0uMWM0LjEtLjEgNi43LTEuOCA3LjgtNS4yLjItLjcuMy0xLjMuMy0xLjl2LTEuMWMtLjEtMi4yLS42LTQtMS44LTUuNC0xLjEtMS40LTIuNi0yLjEtNC41LTIuMmgtMi44ek0xNjQuNSA0Ni42Yy0zLjMtMy4zLTUtNy43LTUtMTMuMiAwLTUuNSAxLjctOS44IDUtMTMuMiAzLjMtMy4zIDcuNy01IDEzLjItNSA0LjUgMCA4LjQgMS4xIDExLjkgMy40bDEgN2gtLjZjLS43LTIuOS0yLjItNS4xLTQuNC02LjZzLTQuOS0yLjMtOC0yLjNjLTQuNCAwLTcuOSAxLjUtMTAuNSA0LjYtMi42IDMtMy45IDctMy45IDEyczEuMyA5IDMuOSAxMi4xYzIuNiAzLjEgNiA0LjYgMTAuMiA0LjcgMy43IDAgNi45LTEgOS40LTMgMi43LTIuMiA0LjMtNS44IDQuOS0xMC44aC40bC0uNiA3LjhjLTMgNS03LjcgNy41LTE0IDcuNS01LjMgMC05LjYtMS43LTEyLjktNXpNMjIxLjggNTAuNGMuOSAwIDEuNi0uMyAyLjItLjkuNi0uNi45LTEuMyAxLTIuMlYzNC40aC0yMC41djEyLjljMCAuOS4zIDEuNiAxIDIuMi42LjYgMS40LjkgMi4zLjloLjd2LjVoLTExdi0uNWguNmMuOSAwIDEuNi0uMyAyLjItLjkuNi0uNi45LTEuMyAxLTIuMlYxOS41YzAtLjktLjQtMS42LTEtMi4yLS42LS42LTEuNC0uOS0yLjItLjloLS42di0uNWgxMXYuNWgtLjdjLS45IDAtMS42LjMtMi4yLjktLjYuNi0uOSAxLjMtMSAyLjJ2MTMuNEgyMjVWMTkuNWMwLTEuMi0uNi0yLjEtMS42LTIuNy0uNS0uMy0xLS40LTEuNi0uNGgtLjZ2LS41aDEwLjl2LjVoLS43Yy0uOSAwLTEuNi4zLTIuMi45LS42LjYtLjkgMS40LTEgMi4ydjI3LjhjMCAuOS40IDEuNiAxIDIuMi42LjYgMS40LjkgMi4yLjloLjd2LjVoLTEwLjl2LS41aC42ek0yMzguOCA1MC40Yy45IDAgMS42LS4zIDIuMi0uOS42LS42LjktMS40IDEtMi4yVjE5LjVjMC0uOS0uNC0xLjYtMS0yLjItLjYtLjYtMS40LS45LTIuMi0uOWgtLjd2LS41aDExdi41aC0uN2MtLjkgMC0xLjYuMy0yLjIuOS0uNi42LS45IDEuMy0xIDIuMnYyNy43YzAgLjkuMyAxLjYgMSAyLjIuNi42IDEuNC45IDIuMy45aC43di41aC0xMXYtLjVoLjZ6TTI3OS41IDE1LjloMTB2LjVoLS41Yy0uOCAwLTEuNi4zLTIuNC44LS44LjUtMS40IDEuMy0xLjkgMi4zbC0xMiAyNi45Yy0xLjIgMi43LTEuOSA0LjQtMS45IDUuMmgtLjRsLTE0LjEtMzJjLS41LTEuMS0xLjEtMS45LTEuOS0yLjQtLjgtLjUtMS42LS44LTIuNS0uOGgtLjR2LS41aDExLjN2LjVoLS41Yy0uNyAwLTEuMi4yLTEuNi43LS40LjUtLjUuOS0uNSAxLjNzLjEuOC4zIDEuMkwyNzEgNDUuN2wxMC45LTI2LjJjLjEtLjQuMi0uOC4yLTEuMiAwLS40LS4yLS44LS41LTEuMy0uNC0uNC0uOS0uNy0xLjYtLjdoLS41di0uNHpNMzE5LjYgNTAuNGguNHYuNWgtMTEuM3YtLjVoLjVjLjcgMCAxLjItLjIgMS42LS43LjQtLjUuNS0uOS41LTEuM3MtLjEtLjgtLjItMS4ybC0zLjItOC41aC0xMy41bC0zLjMgOC42Yy0uMS40LS4yLjgtLjIgMS4yIDAgLjQuMi44LjUgMS4zcy45LjcgMS42LjdoLjR2LjVoLTEwdi0uNWguNWMuOCAwIDEuNy0uMyAyLjUtLjhzMS41LTEuMyAyLTIuNGwxMS4zLTI2LjljMS4yLTIuNyAxLjgtNC40IDEuOC01LjJoLjVsMTMuNCAzMmMuNSAxIDEuMSAxLjggMS45IDIuNHMxLjQuOCAyLjMuOHpNMjk1IDM3LjNoMTIuM2wtNi0xNi4xLTYuMyAxNi4xek0zMzIuNyAxNy41aC0zLjJ2MjkuOGMwIC45LjMgMS42LjkgMi4yLjYuNiAxLjQuOSAyLjMuOWguNnYuNWgtMTF2LS41aC43Yy45IDAgMS42LS4zIDIuMi0uOS42LS42LjktMS4zIDEtMi4yVjE5LjZjMC0uOS0uNC0xLjYtMS0yLjItLjYtLjYtMS40LS45LTIuMi0uOWgtLjdWMTZoMTMuNWMzIDAgNS40LjggNy4yIDIuNSAxLjcgMS43IDIuNiAzLjkgMi42IDYuNiAwIDIuNy0uOCA1LTIuNSA2LjktMS43IDItMy43IDIuOS02IDIuOS41LjIgMS4xLjcgMS44IDEuNHMxLjMgMS40IDEuOCAyLjFjMi45IDQuMSA0LjcgNi42IDUuNiA3LjYuOS45IDEuNSAxLjYgMS44IDEuOS40LjQuOC43IDEuMiAxIC40LjMuOS42IDEuMy44IDEgLjUgMiAuNyAzLjEuN3YuNUgzNTFjLTEuNCAwLTIuOC0uMy00LS44LTEuMi0uNS0yLjItMS0yLjgtMS42LS42LS41LTEuMi0xLjEtMS42LTEuNi0uNS0uNS0xLjctMi4yLTMuNy01LjItMi0yLjktMy4yLTQuNi0zLjUtNS0uMy0uNC0uNy0uOC0xLTEuMi0xLjEtMS4xLTIuMS0xLjctMy4yLTEuN3YtLjVjLjMgMCAuNi4xIDEgLjFzMSAwIDEuNi0uMWM0LjEtLjEgNi43LTEuOCA3LjgtNS4yLjItLjcuMy0xLjMuMy0xLjl2LTEuMWMtLjEtMi4yLS43LTQtMS44LTUuNC0xLjEtMS40LTIuNi0yLjEtNC41LTIuMmgtMi45ek0zNTUuNCA1MC40Yy45IDAgMS42LS4zIDIuMi0uOS42LS42LjktMS40IDEtMi4yVjE5LjVjMC0uOS0uNC0xLjYtMS0yLjItLjYtLjYtMS40LS45LTIuMi0uOWgtLjd2LS41aDExdi41aC0uN2MtLjkgMC0xLjYuMy0yLjIuOS0uNi42LS45IDEuMy0xIDIuMnYyNy43YzAgLjkuMyAxLjYgMSAyLjIuNi42IDEuNC45IDIuMy45aC43di41aC0xMXYtLjVoLjZ6TTQwMi4zIDUwLjloLTEyLjJ2LS41aC42Yy43IDAgMS4zLS4zIDEuNy0xIC4yLS40LjQtLjcuNC0xcy0uMS0uNy0uMy0xbC03LjctMTIuMS03LjcgMTIuMWMtLjIuMy0uMy43LS4zIDEgMCAuNC4xLjcuMyAxIC40LjcgMSAxIDEuNyAxaC43di41aC0xMS40di0uNWguN2MxIDAgMi0uMyAyLjgtLjguOS0uNiAxLjctMS4zIDIuMy0yLjFsOS42LTE0LjItOC44LTEzLjhjLS42LS44LTEuMy0xLjUtMi4yLTIuMS0uOS0uNi0xLjgtLjktMi44LS45aC0uN1YxNmgxMi4xdi41aC0uNmMtLjcgMC0xLjMuMy0xLjcgMS0uMi40LS40LjctLjQgMSAwIC4zLjEuNy4zIDFsNi45IDEwLjggNi44LTEwLjhjLjItLjMuMy0uNy4zLTEgMC0uNC0uMS0uNy0uMy0xLS40LS43LTEtMS0xLjctMWgtLjZWMTZoMTEuM3YuNWgtLjdjLTEgMC0xLjkuMy0yLjguOS0uOS42LTEuNiAxLjMtMi4yIDIuMWwtOC44IDEyLjkgOS43IDE1LjJjLjggMS4xIDEuOCAyIDMgMi41LjcuMyAxLjMuNCAyIC40aC43di40eiIvPjwvZz48L3N2Zz4=";
}

function deleteBackup( $params )
{
  global $sourcePath;
  $pdo = newPDO();

  if ( isset( $params['all'] ) ) {
    createTable( 'backup' );

    $stmt = $pdo->prepare( "SELECT rowid, * FROM backup ORDER BY rowid DESC" );
    $stmt->execute();

    while ( $backup = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
      if ( strlen( $backup['filename'] ) ) unlink( $sourcePath . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . $backup['filename'] );
    }

    $stmt = $pdo->prepare( "DELETE FROM backup" );
    $stmt->execute();
    if ( file_exists( $sourcePath . DIRECTORY_SEPARATOR . 'backup' ) ) deleteDirectory( $sourcePath . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR );
    createDirectory( 'backup' );

    return;
  }

  if ( isset( $params['breakpoint'] ) ) {
    $pdo_remove = newPDO();
    $stmt       = $pdo->prepare( "SELECT rowid, * FROM backup WHERE rowid <= :breakpoint ORDER BY rowid DESC" );
    $stmt->bindParam( 'breakpoint', $params['breakpoint'] );
    $stmt->execute();

    while ( $backup = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
      if ( strlen( $backup['filename'] ) ) unlink( $sourcePath . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . $backup['filename'] );
      $stmt_remove = $pdo_remove->prepare( "DELETE FROM backup WHERE rowid = :rowid" );
      $stmt_remove->bindParam( 'rowid', $backup['rowid'] );
      $stmt_remove->execute();
    }

    return;
  }

  $backups = explode( ',', $params['backups'] );
  foreach ( $backups as $backupId ) {
    $stmt = $pdo->prepare( "SELECT rowid, * FROM backup WHERE rowid = :rowid" );
    $stmt->execute( ['rowid' => $backupId] );

    while ( $backup = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
      if ( strlen( $backup['filename'] ) ) unlink( $sourcePath . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . $backup['filename'] );
    }

    $stmt = $pdo->prepare( "DELETE FROM backup WHERE rowid = :rowid" );
    $stmt->execute( ['rowid' => $backupId] );
  }

  responseAjax();
}

function deleteCustomFile( $filename )
{
  global $sourcePath;
  $filename     = basename( $filename );
  $includesPath = $sourcePath . DIRECTORY_SEPARATOR . 'includes';
  $file         = $includesPath . DIRECTORY_SEPARATOR . $filename;
  if ( !file_exists( $file ) ) return;
  unlink( $file );
  if ( !file_exists( $file ) ) return true;
}

function deleteDirectory( $target )
{
  $files = array_diff( scandir( $target ), array('.', '..') );
  foreach ( $files as $file ) {
    ( is_dir( $target . DIRECTORY_SEPARATOR . $file ) ) ? deleteDirectory( $target . DIRECTORY_SEPARATOR . $file ) : unlink( $target . DIRECTORY_SEPARATOR . $file );
  }
  return rmdir( $target );
}

function detectLanguage()
{
  if ( !empty( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
    $browserLanguages = explode( ',', $_SERVER['HTTP_ACCEPT_LANGUAGE'] );
    $browserLanguages = array_map( function ( $a ) {
      return substr( trim( $a ), 0, 2 );
    }, $browserLanguages );
    return !empty( array_intersect( $browserLanguages, ['ru', 'be', 'uk', 'kk', 'lv', 'lt', 'ky', 'ab', 'uz'] ) ) ? 'ru' : 'en';
  } else {
    return 'en';
  }
}

function doSearchReplaceCode( $params, $taskOffset = 0 )
{
  if ( $params['type'] == 'new' ) {
    return array();
  }

  global $taskIncomplete;
  global $taskIncompleteOffset;
  global $taskStats;
  global $sourcePath;
  global $ACMS;
  $memorySize = getBytesFromHumanSize( ini_get( 'memory_limit' ) );

  if ( !empty( unserialize( $taskStats ) ) ) {
    $stats = unserialize( $taskStats );
  } else {
    $stats = ['pages' => 0, 'result' => ['total_urls' => 0, 'total_matches' => 0, 'limit_reached' => 0], 'total_matches' => 0, 'processed' => 0, 'replaced' => 0, 'removed' => 0, 'total' => 0];
  }

  $result = !empty( $stats['result'] ) ? $stats['result'] : ['total_urls' => 0, 'total_matches' => 0, 'limit_reached' => 0];

  $mimeTypeSql = "'text/html'";
  if ( !empty( $params['text_files_search'] ) ) {
    $mimeTypeSql = "'text/html', 'text/css', 'application/javascript', 'application/x-javascript', 'text/javascript', 'text/plain', 'application/json', 'application/xml', 'text/xml'";
  }

  $pdo = newPDO();
  if ( empty( $stats['total'] ) ) $stats['total'] = $pdo->query( "SELECT COUNT(1) FROM structure WHERE mimetype IN ({$mimeTypeSql})" )->fetchColumn();
  $stmt = $pdo->prepare( "SELECT rowid, * FROM structure WHERE mimetype IN ({$mimeTypeSql}) AND rowid > :taskOffset ORDER BY rowid" );
  $stmt->execute( ['taskOffset' => $taskOffset] );

  while ( $url = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
    if ( $ACMS['ACMS_TIMEOUT'] && ( microtime( true ) - ACMS_START_TIME ) > ( $ACMS['ACMS_TIMEOUT'] - 1 ) ) {
      $stats['result']      = $result;
      $taskStats            = serialize( $stats );
      $taskIncomplete       = true;
      $taskIncompleteOffset = $url['rowid'] - 1;
      return $stats;
    }

    if ( $url['filename'] == '' ) continue;
    $file = $sourcePath . DIRECTORY_SEPARATOR . $url['folder'] . DIRECTORY_SEPARATOR . $url['filename'];

    $params['search_conv']  = convertEncoding( $params['search'], $url['charset'], 'utf-8' );
    $params['replace_conv'] = convertEncoding( $params['replace'], $url['charset'], 'utf-8' );

    $params['search_conv']  = preg_replace( '~(*BSR_ANYCRLF)\R~', "\n", $params['search_conv'] );
    $params['replace_conv'] = preg_replace( '~(*BSR_ANYCRLF)\R~', "\n", $params['replace_conv'] );

    if ( $params['regex'] == 0 ) {
      $params['search_conv']  = preg_quote( $params['search_conv'], '~' );
      $params['replace_conv'] = preg_replace( '/\$(\d)/', '\\\$$1', $params['replace_conv'] );
    }

    if ( $params['type'] == 'search' ) {
      preg_match_all( "~{$params['search_conv']}~is", preg_replace( '~(*BSR_ANYCRLF)\R~', "\n", file_get_contents( $file ) ), $matches, PREG_OFFSET_CAPTURE );

      if ( is_array( $matches[0] ) && count( $matches[0] ) ) {
        if ( !empty( $params['filter']['text'] ) ) {
          foreach ( $params['filter']['text'] as $filterNum => $filterVal ) {
            $filterCur = [
              'param'    => $params['filter']['param'][$filterNum],
              'operator' => $params['filter']['operator'][$filterNum],
              'text'     => $params['filter']['text'][$filterNum],
              'regex'    => $params['filter']['regex'][$filterNum],
            ];
            if ( !strlen( $filterCur['text'] ) ) continue;
            if ( $filterCur['param'] == 'datetime' && strlen( $filterCur['text'] ) < 4 ) continue;
            if ( $filterCur['param'] == 'filesize' ) $filterCur['text'] = getBytesFromHumanSize( $filterCur['text'] );
            if ( $filterCur['param'] == 'text' ) $filterCur['text'] = convertEncoding( $filterCur['text'], $url['charset'], 'utf-8' );
            if ( !$filterCur['regex'] ) $filterCur['text'] = preg_quote( $filterCur['text'], '~' );
            switch ( $filterCur['param'] ) {
              case 'code' :
                preg_match_all( "~{$filterCur['text']}~is", preg_replace( '~(*BSR_ANYCRLF)\R~', "\n", file_get_contents( $file ) ), $advmatches, PREG_OFFSET_CAPTURE );
                if ( !count( $advmatches[0] ) && $filterCur['operator'] == 'contains' ) continue 3;
                if ( count( $advmatches[0] ) && $filterCur['operator'] == 'contains-not' ) continue 3;
                break;
              case 'url' :
                preg_match_all( "~{$filterCur['text']}~is", rawurldecode( $url['request_uri'] ), $advmatches, PREG_OFFSET_CAPTURE );
                if ( !count( $advmatches[0] ) && $filterCur['operator'] == 'contains' ) continue 3;
                if ( count( $advmatches[0] ) && $filterCur['operator'] == 'contains-not' ) continue 3;
                break;
              case 'mime' :
                preg_match_all( "~{$filterCur['text']}~is", $url['mimetype'], $advmatches, PREG_OFFSET_CAPTURE );
                if ( !count( $advmatches[0] ) && $filterCur['operator'] == 'contains' ) continue 3;
                if ( count( $advmatches[0] ) && $filterCur['operator'] == 'contains-not' ) continue 3;
                break;
              case 'datetime' :
                if ( $filterCur['operator'] == 'from' && $url['filetime'] < str_pad( $filterCur['text'], 14, 0 ) ) continue 3;
                if ( $filterCur['operator'] == 'to' && $url['filetime'] > str_pad( $filterCur['text'], 14, 9 ) ) continue 3;
                break;
              case 'filesize' :
                if ( $filterCur['operator'] == 'from' && $url['filesize'] < $filterCur['text'] ) continue 3;
                if ( $filterCur['operator'] == 'to' && $url['filesize'] > $filterCur['text'] ) continue 3;
                break;
              case 'hostname' :
                preg_match_all( "~{$filterCur['text']}~is", $url['hostname'], $advmatches, PREG_OFFSET_CAPTURE );
                if ( !count( $advmatches[0] ) && $filterCur['operator'] == 'contains' ) continue 3;
                if ( count( $advmatches[0] ) && $filterCur['operator'] == 'contains-not' ) continue 3;
                break;
            }
          }
        }

        if ( isset( $params['perform'] ) && $params['perform'] == 'remove' ) {
          removeUrl( $url['rowid'] );
          $stats['removed']++;
          if ( $ACMS['ACMS_TIMEOUT'] && ( microtime( true ) - ACMS_START_TIME ) > ( $ACMS['ACMS_TIMEOUT'] - 1 ) ) {
            $taskStats            = serialize( $stats );
            $taskIncomplete       = true;
            $taskIncompleteOffset = $url['rowid'];
            return $stats;
          }
        }
      }


      foreach ( $matches as $n => $match ) {
        if ( !count( $match ) ) {
          continue;
        }

        unset( $results );
        $results = [];
        for ( $n = 0; $n < count( $match ); $n++ ) {
          $stats['total_matches']++;
          if ( $stats['total_matches'] > $ACMS['ACMS_MATCHES_LIMIT'] ) {
            if ( !$result['limit_reached'] ) $result['limit_reached'] = 1;
            continue;
          }
          $results[] = array(
            'result'   => convertEncoding( $match[$n][0], 'utf-8', $url['charset'] ),
            'position' => $match[$n][1],
          );
        }

        // && ( memory_get_usage() / $memorySize ) < 0.9
        if ( $stats['total_matches'] <= $ACMS['ACMS_MATCHES_LIMIT'] ) $result[] = array(
          'type'        => 'search',
          'rowid'       => $url['rowid'],
          'domain'      => $url['hostname'],
          'request_uri' => $url['request_uri'],
          'results'     => !empty( $results ) ? $results : [],
        );

        $stats['pages']++;

        $result['total_matches'] = $stats['total_matches'];
        $result['total_urls']    = $stats['pages'];

        if ( $ACMS['ACMS_TIMEOUT'] && ( microtime( true ) - ACMS_START_TIME ) > ( $ACMS['ACMS_TIMEOUT'] - 1 ) ) {
          $stats['result']      = $result;
          $taskStats            = serialize( $stats );
          $taskIncomplete       = true;
          $taskIncompleteOffset = $url['rowid'];
          return $stats;
        }
      }
    }

    if ( $params['type'] == 'replace' ) {
      preg_match_all( "~{$params['search_conv']}~is", preg_replace( '~(*BSR_ANYCRLF)\R~', "\n", file_get_contents( $file ) ), $found, PREG_OFFSET_CAPTURE );
      $matches = preg_filter( "~{$params['search_conv']}~is", "{$params['replace_conv']}", preg_replace( '~(*BSR_ANYCRLF)\R~', "\n", file_get_contents( $file ) ), -1, $count );

      if ( !$count ) {
        continue;
      }

      if ( count( $found[0] ) ) {
        if ( !empty( $params['filter']['text'] ) ) {
          foreach ( $params['filter']['text'] as $filterNum => $filterVal ) {
            $filterCur = [
              'param'    => $params['filter']['param'][$filterNum],
              'operator' => $params['filter']['operator'][$filterNum],
              'text'     => $params['filter']['text'][$filterNum],
              'regex'    => $params['filter']['regex'][$filterNum],
            ];
            if ( !strlen( $filterCur['text'] ) ) continue;
            if ( $filterCur['param'] == 'datetime' && strlen( $filterCur['text'] ) < 4 ) continue;
            if ( $filterCur['param'] == 'filesize' ) $filterCur['text'] = getBytesFromHumanSize( $filterCur['text'] );
            if ( $filterCur['param'] == 'text' ) $filterCur['text'] = convertEncoding( $filterCur['text'], $url['charset'], 'utf-8' );
            if ( !$filterCur['regex'] ) $filterCur['text'] = preg_quote( $filterCur['text'], '~' );
            switch ( $filterCur['param'] ) {
              case 'code' :
                preg_match_all( "~{$filterCur['text']}~is", preg_replace( '~(*BSR_ANYCRLF)\R~', "\n", file_get_contents( $file ) ), $advmatches, PREG_OFFSET_CAPTURE );
                if ( !count( $advmatches[0] ) && $filterCur['operator'] == 'contains' ) continue 3;
                if ( count( $advmatches[0] ) && $filterCur['operator'] == 'contains-not' ) continue 3;
                break;
              case 'url' :
                preg_match_all( "~{$filterCur['text']}~is", rawurldecode( $url['request_uri'] ), $advmatches, PREG_OFFSET_CAPTURE );
                if ( !count( $advmatches[0] ) && $filterCur['operator'] == 'contains' ) continue 3;
                if ( count( $advmatches[0] ) && $filterCur['operator'] == 'contains-not' ) continue 3;
                break;
              case 'mime' :
                preg_match_all( "~{$filterCur['text']}~is", $url['mimetype'], $advmatches, PREG_OFFSET_CAPTURE );
                if ( !count( $advmatches[0] ) && $filterCur['operator'] == 'contains' ) continue 3;
                if ( count( $advmatches[0] ) && $filterCur['operator'] == 'contains-not' ) continue 3;
                break;
              case 'datetime' :
                if ( $filterCur['operator'] == 'from' && $url['filetime'] < str_pad( $filterCur['text'], 14, 0 ) ) continue 3;
                if ( $filterCur['operator'] == 'to' && $url['filetime'] > str_pad( $filterCur['text'], 14, 9 ) ) continue 3;
                break;
              case 'filesize' :
                if ( $filterCur['operator'] == 'from' && $url['filesize'] < $filterCur['text'] ) continue 3;
                if ( $filterCur['operator'] == 'to' && $url['filesize'] > $filterCur['text'] ) continue 3;
                break;
              case 'hostname' :
                preg_match_all( "~{$filterCur['text']}~is", $url['hostname'], $advmatches, PREG_OFFSET_CAPTURE );
                if ( !count( $advmatches[0] ) && $filterCur['operator'] == 'contains' ) continue 3;
                if ( count( $advmatches[0] ) && $filterCur['operator'] == 'contains-not' ) continue 3;
                break;
            }
          }
        }
      }

      unset( $results );
      $results = [];
      for ( $n = 0; $n < $count; $n++ ) {
        $stats['total_matches']++;
        if ( $stats['total_matches'] > $ACMS['ACMS_MATCHES_LIMIT'] ) {
          if ( !$result['limit_reached'] ) $result['limit_reached'] = 1;
          continue;
        }
        $results[] = array(
          'original' => convertEncoding( $found[0][$n][0], 'utf-8', $url['charset'] ),
          'position' => $found[0][$n][1],
          'result'   => convertEncoding( preg_replace( "~{$params['search_conv']}~is", "{$params['replace_conv']}", $found[0][$n][0] ), 'utf-8', $url['charset'] ),
        );
      }

      if ( $stats['total_matches'] <= $ACMS['ACMS_MATCHES_LIMIT'] ) $result[] = array(
        'type'        => 'replace',
        'rowid'       => $url['rowid'],
        'domain'      => $url['hostname'],
        'request_uri' => $url['request_uri'],
        'count'       => $count,
        'results'     => $results,
      );


      if ( isset( $params['perform'] ) && $params['perform'] == 'replace' ) {
        backupFile( $url['rowid'], 'replace' );
        file_put_contents( $file, $matches );
        updateFilesize( $url['rowid'], filesize( $file ) );
        $stats['replaced']++;
      }

      $stats['pages']++;
      $stats['processed']++;

      $result['total_matches'] = $stats['total_matches'];
      $result['total_urls']    = $stats['pages'];

      if ( $ACMS['ACMS_TIMEOUT'] && ( microtime( true ) - ACMS_START_TIME ) > ( $ACMS['ACMS_TIMEOUT'] - 1 ) ) {
        $stats['result']      = $result;
        $taskStats            = serialize( $stats );
        $taskIncomplete       = true;
        $taskIncompleteOffset = $url['rowid'];
        return $stats;
      }
    }
  }

  if ( $stats['replaced'] && isset( $params['perform'] ) && $params['perform'] == 'replace' ) createBackupBreakpoint( L( 'CODE Replaces' ) . '. ' . sprintf( L( 'Processed: %s' ), number_format( $stats['replaced'], 0 ) ) );
  if ( $stats['removed'] && isset( $params['perform'] ) && $params['perform'] == 'remove' ) createBackupBreakpoint( L( 'Remove all found URLs' ) . '. ' . sprintf( L( 'Processed: %s' ), number_format( $stats['removed'], 0 ) ) );
  return $result;
}

function doSearchReplaceUrls( $params, $taskOffset = 0 )
{
  if ( $params['type'] == 'new' ) {
    return array();
  }

  global $taskIncomplete;
  global $taskIncompleteOffset;
  global $taskStats;
  global $sourcePath;
  global $ACMS;

  if ( !empty( unserialize( $taskStats ) ) ) {
    $stats = unserialize( $taskStats );
  } else {
    $stats = ['pages' => 0, 'result' => ['total_urls' => 0, 'total_matches' => 0, 'limit_reached' => 0], 'total_matches' => 0, 'processed' => 0, 'replaced' => 0, 'removed' => 0, 'total' => 0];
  }

  $result = !empty( $stats['result'] ) ? $stats['result'] : ['total_urls' => 0, 'total_matches' => 0, 'limit_reached' => 0];

  if ( $params['regex'] == 0 ) {
    $params['search']  = preg_quote( $params['search'], '~' );
    $params['replace'] = preg_replace( '/\$(\d)/', '\\\$$1', $params['replace'] );
  }

  $pdo = newPDO();
  if ( empty( $stats['total'] ) ) $stats['total'] = $pdo->query( "SELECT COUNT(1) FROM structure" )->fetchColumn();
  $stmt = $pdo->prepare( "SELECT rowid, * FROM structure WHERE rowid > :taskOffset ORDER BY rowid" );
  $stmt->execute( ['taskOffset' => $taskOffset] );

  while ( $url = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
    if ( $ACMS['ACMS_TIMEOUT'] && ( microtime( true ) - ACMS_START_TIME ) > ( $ACMS['ACMS_TIMEOUT'] - 1 ) ) {
      $stats['result']      = $result;
      $taskStats            = serialize( $stats );
      $taskIncomplete       = true;
      $taskIncompleteOffset = $url['rowid'] - 1;
      return $stats;
    }
    $taskIncompleteOffset = $url['rowid'];

    if ( $params['type'] == 'search' ) {
      preg_match_all( "~{$params['search']}~is", rawurldecode( $url['request_uri'] ), $matches, PREG_OFFSET_CAPTURE );

      if ( is_array( $matches[0] ) && count( $matches[0] ) ) {
        if ( !empty( $params['filter']['text'] ) ) {
          foreach ( $params['filter']['text'] as $filterNum => $filterVal ) {
            $filterCur = [
              'param'    => $params['filter']['param'][$filterNum],
              'operator' => $params['filter']['operator'][$filterNum],
              'text'     => $params['filter']['text'][$filterNum],
              'regex'    => $params['filter']['regex'][$filterNum],
            ];
            if ( !strlen( $filterCur['text'] ) ) continue;
            if ( $filterCur['param'] == 'datetime' && strlen( $filterCur['text'] ) < 4 ) continue;
            if ( $filterCur['param'] == 'filesize' ) $filterCur['text'] = getBytesFromHumanSize( $filterCur['text'] );
            if ( $filterCur['param'] == 'text' ) $filterCur['text'] = convertEncoding( $filterCur['text'], $url['charset'], 'utf-8' );
            if ( !$filterCur['regex'] ) $filterCur['text'] = preg_quote( $filterCur['text'], '~' );
            switch ( $filterCur['param'] ) {
              case 'code' :
                if ( ( $url['filename'] == '' || $url['redirect'] != '' ) && $filterCur['operator'] == 'contains' ) continue 3;
                if ( ( $url['filename'] == '' || $url['redirect'] != '' ) && $filterCur['operator'] == 'contains-not' ) break;
                $file = $sourcePath . DIRECTORY_SEPARATOR . $url['folder'] . DIRECTORY_SEPARATOR . $url['filename'];
                preg_match_all( "~{$filterCur['text']}~is", preg_replace( '~(*BSR_ANYCRLF)\R~', "\n", file_get_contents( $file ) ), $advmatches, PREG_OFFSET_CAPTURE );
                if ( !count( $advmatches[0] ) && $filterCur['operator'] == 'contains' ) continue 3;
                if ( count( $advmatches[0] ) && $filterCur['operator'] == 'contains-not' ) continue 3;
                break;
              case 'url' :
                preg_match_all( "~{$filterCur['text']}~is", rawurldecode( $url['request_uri'] ), $advmatches, PREG_OFFSET_CAPTURE );
                if ( !count( $advmatches[0] ) && $filterCur['operator'] == 'contains' ) continue 3;
                if ( count( $advmatches[0] ) && $filterCur['operator'] == 'contains-not' ) continue 3;
                break;
              case 'mime' :
                preg_match_all( "~{$filterCur['text']}~is", $url['mimetype'], $advmatches, PREG_OFFSET_CAPTURE );
                if ( !count( $advmatches[0] ) && $filterCur['operator'] == 'contains' ) continue 3;
                if ( count( $advmatches[0] ) && $filterCur['operator'] == 'contains-not' ) continue 3;
                break;
              case 'datetime' :
                if ( $filterCur['operator'] == 'from' && $url['filetime'] < str_pad( $filterCur['text'], 14, 0 ) ) continue 3;
                if ( $filterCur['operator'] == 'to' && $url['filetime'] > str_pad( $filterCur['text'], 14, 0 ) ) continue 3;
                break;
              case 'filesize' :
                if ( $filterCur['operator'] == 'from' && $url['filesize'] < $filterCur['text'] ) continue 3;
                if ( $filterCur['operator'] == 'to' && $url['filesize'] > $filterCur['text'] ) continue 3;
                break;
              case 'hostname' :
                preg_match_all( "~{$filterCur['text']}~is", $url['hostname'], $advmatches, PREG_OFFSET_CAPTURE );
                if ( !count( $advmatches[0] ) && $filterCur['operator'] == 'contains' ) continue 3;
                if ( count( $advmatches[0] ) && $filterCur['operator'] == 'contains-not' ) continue 3;
                break;
            }
          }
        }

        if ( isset( $params['perform'] ) && $params['perform'] == 'remove' ) {
          removeUrl( $url['rowid'] );
          $stats['removed']++;
          if ( $ACMS['ACMS_TIMEOUT'] && ( microtime( true ) - ACMS_START_TIME ) > ( $ACMS['ACMS_TIMEOUT'] - 1 ) ) {
            $taskStats            = serialize( $stats );
            $taskIncomplete       = true;
            $taskIncompleteOffset = $url['rowid'];
            return $stats;
          }
        }
      }

      foreach ( $matches as $n => $match ) {
        if ( !count( $match ) ) {
          continue;
        }

        unset( $results );
        $results = [];
        for ( $n = 0; $n < count( $match ); $n++ ) {
          $stats['total_matches']++;
          if ( $stats['total_matches'] > $ACMS['ACMS_MATCHES_LIMIT'] ) {
            if ( !$result['limit_reached'] ) $result['limit_reached'] = 1;
            continue;
          }
          $results[] = array(
            'result'   => $match[$n][0],
            'position' => $match[$n][1],
          );
        }

        if ( $stats['total_matches'] <= $ACMS['ACMS_MATCHES_LIMIT'] ) $result[] = array(
          'type'        => 'search',
          'rowid'       => $url['rowid'],
          'domain'      => $url['hostname'],
          'request_uri' => $url['request_uri'],
          'results'     => !empty( $results ) ? $results : [],
        );

        $stats['pages']++;

        $result['total_matches'] = $stats['total_matches'];
        $result['total_urls']    = $stats['pages'];

        if ( $ACMS['ACMS_TIMEOUT'] && ( microtime( true ) - ACMS_START_TIME ) > ( $ACMS['ACMS_TIMEOUT'] - 1 ) ) {
          $stats['result']      = $result;
          $taskStats            = serialize( $stats );
          $taskIncomplete       = true;
          $taskIncompleteOffset = $url['rowid'];
          return $stats;
        }
      }
    }

    if ( $params['type'] == 'replace' ) {
      preg_match_all( "~{$params['search']}~is", rawurldecode( $url['request_uri'] ), $found, PREG_OFFSET_CAPTURE );
      $matches = preg_filter( "~{$params['search']}~is", "{$params['replace']}", rawurldecode( $url['request_uri'] ), -1, $count );

      if ( count( $found[0] ) ) {
        if ( !empty( $params['filter']['text'] ) ) {
          foreach ( $params['filter']['text'] as $filterNum => $filterVal ) {
            $filterCur = [
              'param'    => $params['filter']['param'][$filterNum],
              'operator' => $params['filter']['operator'][$filterNum],
              'text'     => $params['filter']['text'][$filterNum],
              'regex'    => $params['filter']['regex'][$filterNum],
            ];
            if ( !strlen( $filterCur['text'] ) ) continue;
            if ( $filterCur['param'] == 'datetime' && strlen( $filterCur['text'] ) < 4 ) continue;
            if ( $filterCur['param'] == 'filesize' ) $filterCur['text'] = getBytesFromHumanSize( $filterCur['text'] );
            if ( $filterCur['param'] == 'text' ) $filterCur['text'] = convertEncoding( $filterCur['text'], $url['charset'], 'utf-8' );
            if ( !$filterCur['regex'] ) $filterCur['text'] = preg_quote( $filterCur['text'], '~' );
            switch ( $filterCur['param'] ) {
              case 'code' :
                if ( ( $url['filename'] == '' || $url['redirect'] != '' ) && $filterCur['operator'] == 'contains' ) continue 3;
                if ( ( $url['filename'] == '' || $url['redirect'] != '' ) && $filterCur['operator'] == 'contains-not' ) break;
                $file = $sourcePath . DIRECTORY_SEPARATOR . $url['folder'] . DIRECTORY_SEPARATOR . $url['filename'];
                preg_match_all( "~{$filterCur['text']}~is", preg_replace( '~(*BSR_ANYCRLF)\R~', "\n", file_get_contents( $file ) ), $advmatches, PREG_OFFSET_CAPTURE );
                if ( !count( $advmatches[0] ) && $filterCur['operator'] == 'contains' ) continue 3;
                if ( count( $advmatches[0] ) && $filterCur['operator'] == 'contains-not' ) continue 3;
                break;
              case 'url' :
                preg_match_all( "~{$filterCur['text']}~is", rawurldecode( $url['request_uri'] ), $advmatches, PREG_OFFSET_CAPTURE );
                if ( !count( $advmatches[0] ) && $filterCur['operator'] == 'contains' ) continue 3;
                if ( count( $advmatches[0] ) && $filterCur['operator'] == 'contains-not' ) continue 3;
                break;
              case 'mime' :
                preg_match_all( "~{$filterCur['text']}~is", $url['mimetype'], $advmatches, PREG_OFFSET_CAPTURE );
                if ( !count( $advmatches[0] ) && $filterCur['operator'] == 'contains' ) continue 3;
                if ( count( $advmatches[0] ) && $filterCur['operator'] == 'contains-not' ) continue 3;
                break;
              case 'datetime' :
                if ( $filterCur['operator'] == 'from' && $url['filetime'] < str_pad( $filterCur['text'], 14, 0 ) ) continue 3;
                if ( $filterCur['operator'] == 'to' && $url['filetime'] > str_pad( $filterCur['text'], 14, 0 ) ) continue 3;
                break;
              case 'filesize' :
                if ( $filterCur['operator'] == 'from' && $url['filesize'] < $filterCur['text'] ) continue 3;
                if ( $filterCur['operator'] == 'to' && $url['filesize'] > $filterCur['text'] ) continue 3;
                break;
              case 'hostname' :
                preg_match_all( "~{$filterCur['text']}~is", $url['hostname'], $advmatches, PREG_OFFSET_CAPTURE );
                if ( !count( $advmatches[0] ) && $filterCur['operator'] == 'contains' ) continue 3;
                if ( count( $advmatches[0] ) && $filterCur['operator'] == 'contains-not' ) continue 3;
                break;
            }
          }
        }
      }

      if ( !$count ) {
        continue;
      }

      unset( $results );
      $results = [];
      for ( $n = 0; $n < $count; $n++ ) {
        $stats['total_matches']++;
        if ( $stats['total_matches'] > $ACMS['ACMS_MATCHES_LIMIT'] ) {
          if ( !$result['limit_reached'] ) $result['limit_reached'] = 1;
          continue;
        }
        $results[] = array(
          'original' => $found[0][$n][0],
          'position' => $found[0][$n][1],
          'result'   => preg_replace( "~{$params['search']}~is", "{$params['replace']}", $found[0][$n][0] ),
        );
      }

      $request_uri_new         = encodePath( preg_replace( "~{$params['search']}~is", "{$params['replace']}", rawurldecode( $url['request_uri'] ) ) );
      $request_uri_new_decoded = rawurldecode( $request_uri_new );
      $request_uri_new_valid   = substr( $request_uri_new, 0, 1 ) === '/' && filter_var( 'http://domain' . $request_uri_new, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED );

      if ( $stats['total_matches'] <= $ACMS['ACMS_MATCHES_LIMIT'] ) $result[] = array(
        'type'        => 'replace',
        'rowid'       => $url['rowid'],
        'domain'      => $url['hostname'],
        'request_uri' => $url['request_uri'],
        'replace_uri' => encodePath( preg_replace( "~{$params['search']}~is", "{$params['replace']}", rawurldecode( $url['request_uri'] ) ) ),
        'valid_uri'   => $request_uri_new_valid,
        'count'       => $count,
        'results'     => $results,
      );

      if ( isset( $params['perform'] ) && $params['perform'] == 'replace' && $request_uri_new_valid ) {
        $url_existing = getUrlByPath( $url['hostname'], $request_uri_new );

        // simple rename
        if ( !$url_existing && $url ) {
          $url['original_filename'] = $url['filename'];
          $url['urlID']             = $url['rowid'];
          $url['url']               = $url['protocol'] . '://' . $url['hostname'] . $request_uri_new;
          $url['request_uri']       = $request_uri_new_decoded;
          updateUrlSettings( $url );
        } else {
          $url_existing = getUrl( $url_existing['rowid'] );
          if ( $url_existing && $url && !empty( $params['replaceUrl'] ) && $url_existing['rowid'] != $url['rowid'] ) {
            if ( $url_existing['filetime'] < $url['filetime'] ) {
              removeUrl( $url_existing['rowid'] );
              $url['original_filename'] = $url['filename'];
              $url['urlID']             = $url['rowid'];
              $url['url']               = $url['protocol'] . '://' . $url['hostname'] . $request_uri_new;
              $url['request_uri']       = $request_uri_new_decoded;
              updateUrlSettings( $url );
            } else {
              removeUrl( $url['rowid'] );
            }
          }
        }
        $stats['replaced']++;
      }

      $result['total_matches'] = $stats['total_matches'];
      $result['total_urls']    = $stats['replaced'];
    }
    if ( isset( $params['perform'] ) && $params['perform'] == 'replace' ) {
      $stats['pages']++;
      if ( $ACMS['ACMS_TIMEOUT'] && ( microtime( true ) - ACMS_START_TIME ) > ( $ACMS['ACMS_TIMEOUT'] - 1 ) ) {
        $stats['result']      = $result;
        $taskStats            = serialize( $stats );
        $taskIncomplete       = true;
        $taskIncompleteOffset = $url['rowid'];
        return $stats;
      }
    }
    $stats['processed']++;
  }

  if ( $stats['replaced'] && isset( $params['perform'] ) && $params['perform'] == 'replace' ) createBackupBreakpoint( L( 'URLs Replaces' ) . '. ' . sprintf( L( 'Processed: %s' ), number_format( $stats['replaced'], 0 ) ) );
  if ( $stats['pages'] && isset( $params['perform'] ) && $params['perform'] == 'remove' ) createBackupBreakpoint( L( 'Remove all found URLs' ) . '. ' . sprintf( L( 'Processed: %s' ), number_format( $stats['pages'], 0 ) ) );
  return $result;
}

function downloadFile( $url, $dest, $taskOffset = 0 )
{
  global $taskIncomplete;
  global $taskIncompleteOffset;
  global $taskStats;
  global $ACMS;

  if ( !empty( unserialize( $taskStats ) ) ) {
    $stats = unserialize( $taskStats );
  } else {
    $stats = ['size' => 0, 'pages' => 0, 'processed' => 0, 'total' => 0];
  }

  $options = array(
    CURLOPT_FILE           => is_resource( $dest ) ? $dest : ( $taskOffset ? fopen( $dest, 'a+' ) : fopen( $dest, 'w+' ) ),
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_URL            => $url,
    CURLOPT_FAILONERROR    => true,
    CURLOPT_RESUME_FROM    => $taskOffset ? $taskOffset : 0,
    CURLOPT_TIMEOUT        => $ACMS['ACMS_TIMEOUT'] ? ( $ACMS['ACMS_TIMEOUT'] - 1 ) : 0,
    CURLOPT_USERAGENT      => "Archivarix-CMS/" . ACMS_VERSION . " (+https://archivarix.com/en/cms/)",
  );

  $ch = curl_init();
  curl_setopt_array( $ch, $options );
  $return = curl_exec( $ch );

  if ( $return === false ) {
    if ( curl_errno( $ch ) == 28 ) {
      $stats['size'] += curl_getinfo( $ch )['size_download'];
      $stats['pages']++;
      $stats['processed']   = $stats['size'];
      $stats['total']       = curl_getinfo( $ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD );
      $taskIncomplete       = true;
      $taskIncompleteOffset = $stats['size'];
      $taskStats            = serialize( $stats );
      return false;
    }
    return true;
  } else {
    return true;
  }
}

function downloadFromSerial( $uuid, $taskOffset = 0 )
{
  global $sourcePath;
  $uuid = strtoupper( trim( preg_replace( '~[^0-9a-z]~i', '', $uuid ) ) );
  if ( !preg_match( '~[0-9A-Z]{16}~', $uuid ) ) return;
  createDirectory( 'imports' );
  downloadFile( 'https://download.archivarix.cloud/restores/' . $uuid[0] . '/' . $uuid[1] . '/' . $uuid . '.zip', $sourcePath . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . $uuid . '.zip', $taskOffset );
  return $uuid;
}

function dropTable( $tableName, $pdo = null )
{
  if ( $pdo == null ) $pdo = newPDO();

  switch ( $tableName ) :
    case 'backup' :
      $pdo->exec( "DROP TABLE backup" );
      break;
    case 'templates' :
      $pdo->exec( "DROP TABLE templates" );
      break;
    case 'meta' :
      $pdo->exec( "DROP TABLE meta" );
      break;
    case 'structure' :
      $pdo->exec( "DROP TABLE structure" );
      break;
    case 'settings' :
      $pdo->exec( "DROP TABLE settings" );
      break;
    case 'missing' :
      $pdo->exec( "DROP TABLE missing" );
      break;
  endswitch;
}

function encodePath( $pathDecoded )
{
  $pathEncoded = '';
  $path        = parse_url( $pathDecoded );
  if ( isset( $path['path'] ) ) {
    $pathEncoded = implode( '/', array_map( 'rawurlencode', explode( '/', $path['path'] ) ) );
  }
  if ( isset( $path['query'] ) ) {
    parse_str( $path['query'], $queryParts );
    // [TODO] keep parameters with missing equal sign fix, not elegant at all
    foreach ( $queryParts as $queryParam => $queryValue ) {
      if ( $queryValue === "" ) $queryParts[$queryParam] = 'ARCHIVARIX_REMOVE_EQUAL_SIGN';
    }
    $pathEncoded .= '?' . http_build_query( $queryParts, '', '&', PHP_QUERY_RFC3986 );
    $pathEncoded = str_replace( '=ARCHIVARIX_REMOVE_EQUAL_SIGN', '', $pathEncoded );
  }
  return $pathEncoded;
}

function encodeUrl( $url )
{
  $parts = parse_url( $url );
  return
    ( !empty( $parts['scheme'] ) ? $parts['scheme'] . '://' : '' ) .
    ( !empty( $parts['host'] ) ? $parts['host'] : '' ) .
    encodePath( ( !empty( $parts['path'] ) ? $parts['path'] : '' ) . ( !empty( $parts['query'] ) ? '?' . $parts['query'] : '' ) );
}

function escapeArrayValues( &$value )
{
  $value = htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
}

function flattenArray( $array, $prefix = '' )
{
  $result = array();
  foreach ( $array as $key => $value ) {
    if ( is_array( $value ) ) {
      $result = $result + flattenArray( $value, $prefix . $key . ',' );
    } else {
      $result[$prefix . $key] = $value;
    }
  }
  return $result;
}

function funConvertWebsite()
{
  // [TODO] just for fun, use at your own risk, not ready at all
  global $sourcePath;
  $output = $sourcePath . DIRECTORY_SEPARATOR . 'export' . DIRECTORY_SEPARATOR;
  deleteDirectory( $output );
  $pdo  = newPDO();
  $stmt = $pdo->prepare( "SELECT * FROM structure ORDER BY filetime" );
  $stmt->execute();

  while ( $url = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
    $parsed   = parse_url( $url['url'] );
    $pathinfo = pathinfo( $parsed['path'] );
    if ( !file_exists( $output . $pathinfo['dirname'] ) ) {
      mkdir( $output . $pathinfo['dirname'], 0777, true );
      //addWarning( $output . $pathinfo['dirname'] );
    }
    if ( strlen( $pathinfo['basename'] ) == 0 || !isset( $pathinfo['extension'] ) ) {
      $filename = $pathinfo['basename'] . DIRECTORY_SEPARATOR . 'index.html';
    } else {
      $filename = rawurldecode( $pathinfo['basename'] );
    }
    $source      = $sourcePath . DIRECTORY_SEPARATOR . $url['folder'] . DIRECTORY_SEPARATOR . $url['filename'];
    $destination = str_replace( DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $output . $pathinfo['dirname'] . DIRECTORY_SEPARATOR . $filename );
    //addWarning( "Path basename: {$pathinfo['basename']}<br>{$destination}" );
    if ( !file_exists( pathinfo( $destination, PATHINFO_DIRNAME ) ) ) {
      mkdir( pathinfo( $destination, PATHINFO_DIRNAME ), 0777, true );
    }
    copy( $source, $destination );
  }
}

function funImportMD( $params, $taskOffset = 0 )
{
  // nothing here
}

function getAbsolutePath( $pageUrl, $href )
{
  $pageScheme = parse_url( $pageUrl, PHP_URL_SCHEME );

  if ( mb_strpos( $href, '#' ) !== false ) {
    $href = mb_substr( $href, 0, mb_strpos( $href, '#' ) );
  }
  if ( !mb_strlen( $href ) ) {
    return $pageUrl;
  }
  if ( !parse_url( $pageUrl, PHP_URL_PATH ) ) {
    $pageUrl = $pageUrl . '/';
  }
  if ( parse_url( $href, PHP_URL_SCHEME ) ) {
    return preg_replace( '~^[^/]+~', $pageScheme, $href );
  }
  if ( parse_url( $href, PHP_URL_HOST ) && mb_substr( $href, 0, 2 ) == '//' ) {
    return parse_url( $pageUrl, PHP_URL_SCHEME ) . ':' . $href;
  }
  if ( mb_substr( $href, 0, 1 ) == '/' ) {
    return parse_url( $pageUrl, PHP_URL_SCHEME ) . '://' . parse_url( $pageUrl, PHP_URL_HOST ) . $href;
  }
  if ( mb_substr( $href, 0, 2 ) == './' ) {
    $href = preg_replace( '~^(\./)+~', '', $href );
  }
  if ( mb_substr( $href, 0, 3 ) == '../' ) {
    preg_match( '~^(\.\./)+~', $href, $matches );
    $levelsUp = mb_substr_count( $matches[0], '../' );
    $basePath = parse_url( $pageUrl, PHP_URL_PATH );
    for ( $i = 0; $i <= $levelsUp; $i++ ) {
      $basePath = mb_substr( $basePath, 0, strrpos( $basePath, '/' ) );
    }
    return parse_url( $pageUrl, PHP_URL_SCHEME ) . '://' . parse_url( $pageUrl, PHP_URL_HOST ) . $basePath . '/' . preg_replace( '~^(\.\./)+~', '', $href );
  }
  return parse_url( $pageUrl, PHP_URL_SCHEME ) . '://' . parse_url( $pageUrl, PHP_URL_HOST ) . mb_substr( parse_url( $pageUrl, PHP_URL_PATH ), 0, strrpos( parse_url( $pageUrl, PHP_URL_PATH ), '/' ) ) . '/' . $href;
}

function getAllDomains()
{
  global $uuidSettings;
  $pdo  = newPDO();
  $stmt = $pdo->prepare( 'SELECT DISTINCT hostname FROM structure ORDER BY (hostname = :hostname) DESC, (hostname = :wwwhostname) DESC, hostname' );
  $stmt->execute( ['hostname' => $uuidSettings['domain'], 'wwwhostname' => 'www.' . $uuidSettings['domain']] );

  $domains = array();

  while ( $domain = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
    $domains[$domain['hostname']] = array();
  }

  foreach ( $domains as $domain => $val ) {
    $pathUrls = [];
    $paths    = [];

    $domains[$domain]['urls'] = getAllUrls( $domain );

    foreach ( $domains[$domain]['urls'] as $url ) {
      $pathUrls[$url['request_uri']] = $url;
      $pathString                    = ltrim( rtrim( $url['request_uri'], '/' ), '/' );
      $pathParts                     = explode( '/', $pathString );
      if ( substr( $url['request_uri'], -1 ) == '/' ) {
        $pathParts[count( $pathParts ) - 1] = $pathParts[count( $pathParts ) - 1] . '/';
      }
      $path = [array_pop( $pathParts )];
      foreach ( array_reverse( $pathParts ) as $pathPart ) {
        $path = ['_' . $pathPart => $path];
      }
      $paths[] = $path;
    }
    $domains[$domain]['tree']     = count( $paths ) ? call_user_func_array( 'array_merge_recursive', $paths ) : [];
    $domains[$domain]['pathUrls'] = $pathUrls;
    $domains[$domain]['safeName'] = preg_replace( '~[^a-z0-9]~', '_', $domain );

    unset( $pathUrls );
    unset( $paths );
  }

  return $domains;
}

function getAllUrls( $domain )
{
  global $ACMS;
  $pdo  = newPDO();
  $urls = array();

  $stmt = $pdo->prepare( 'SELECT COUNT(1) FROM structure WHERE hostname = :domain' );
  $stmt->execute( ['domain' => $domain] );

  global $urlsTotal;
  $urlsTotal[$domain] = $stmt->fetchColumn();

  global $urlOffsets;
  if ( key_exists( $domain, $urlOffsets ) ) {
    $offset = ( $urlOffsets[$domain] - 1 ) * $ACMS['ACMS_URLS_LIMIT'];
  } else {
    $offset = 0;
  }

  $stmt = $pdo->prepare( 'SELECT rowid, * FROM structure WHERE hostname = :domain ORDER BY request_uri LIMIT :offset, :limit' );
  $stmt->execute( ['domain' => $domain, 'offset' => $offset, 'limit' => $ACMS['ACMS_URLS_LIMIT']] );

  while ( $url = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
    $documentName = pathinfo( $url['request_uri'], PATHINFO_BASENAME );
    $documentPath = pathinfo( parse_url( $url['request_uri'], PHP_URL_PATH ), PATHINFO_DIRNAME );
    $documentPath = $documentPath . ( $documentPath == '/' ? '' : '/' );

    $url['name']         = $documentName;
    $url['virtual_path'] = $documentPath;
    $urls[]              = $url;
  }

  return $urls;
}

function getBytesFromHumanSize( $humanSize )
{
  $humanSize = trim( $humanSize );
  return intval( preg_replace_callback( '~^([\d.]+)\s*(?:([ptgmk]?[i]?)b?)?$~i', function ( $m ) {
    switch ( strtolower( $m[2] ) ) {
      case 'p' :
        $m[1] *= 1024;
      case 't' :
        $m[1] *= 1024;
      case 'g' :
        $m[1] *= 1024;
      case 'm' :
        $m[1] *= 1024;
      case 'k' :
        $m[1] *= 1024;
        break;
      case 'pi' :
        $m[1] *= 1000;
      case 'ti' :
        $m[1] *= 1000;
      case 'gi' :
        $m[1] *= 1000;
      case 'mi' :
        $m[1] *= 1000;
      case 'ki' :
        $m[1] *= 1000;
        break;
    }
    return intval( $m[1] );
  }, $humanSize ) );

  // remove
  $last = strtolower( $humanSize[strlen( $humanSize ) - 1] );
  switch ( $last ) {
    case 'g':
      $humanSize = intval( $humanSize ) * 1024;
    case 'm':
      $humanSize = intval( $humanSize ) * 1024;
    case 'k':
      $humanSize = intval( $humanSize ) * 1024;
  }
  return $humanSize;
}

function getCustomFileMeta( $filename )
{
  global $sourcePath;
  global $documentMimeType;
  $filename = basename( $filename );
  if ( empty( $filename ) ) return;
  $file = $includesPath = $sourcePath . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . $filename;
  if ( !file_exists( $file ) ) return;
  if ( is_dir( $file ) ) return;
  $fileStats        = stat( $file );
  $meta             = [
    'mimetype'      => function_exists( 'mime_content_type' ) ? mime_content_type( $file ) : 'text/plain',
    'filename'      => $filename,
    'mtime'         => $fileStats['mtime'],
    'size'          => $fileStats['size'],
    'is_dir'        => is_dir( $file ),
    'is_readable'   => is_readable( $file ),
    'is_writable'   => is_writable( $file ),
    'is_executable' => is_executable( $file ),
    'data'          => file_get_contents( $file ),
  ];
  $documentMimeType = $meta['mimetype'];
  return $meta;
}

function getCustomFiles()
{
  global $sourcePath;
  $includesPath = $sourcePath . DIRECTORY_SEPARATOR . 'includes';
  $result       = [];
  if ( !file_exists( $includesPath ) || !is_dir( $includesPath ) ) return $result;
  $files = array_diff( scandir( $includesPath ), ['.', '..'] );
  foreach ( $files as $filename ) {
    $file      = $includesPath . DIRECTORY_SEPARATOR . $filename;
    $fileStats = stat( $file );

    if ( is_dir( $file ) ) $mime = ['extension' => '', 'icon' => 'fa-folder', 'type' => 'folder'];
    elseif ( filesize( $file ) == 0 ) $mime = getMimeInfo( 'text/plain' );
    elseif ( function_exists( 'mime_content_type' ) ) $mime = getMimeInfo( mime_content_type( $file ) );
    else $mime = getMimeInfo( 'text/plain' );

    $result[] = [
      'id'            => getRandomString( 8 ),
      'mime'          => $mime,
      'mimetype'      => function_exists( 'mime_content_type' ) ? mime_content_type( $file ) : 'text/plain',
      'filename'      => $filename,
      'mtime'         => $fileStats['mtime'],
      'size'          => $fileStats['size'],
      'is_dir'        => is_dir( $file ),
      'is_readable'   => is_readable( $file ),
      'is_writable'   => is_writable( $file ),
      'is_executable' => is_executable( $file ),
      'permissions'   => ( is_readable( $file ) ? 'r' : '-' ) . ( is_writable( $file ) ? 'w' : '-' ) . ( is_executable( $file ) ? 'x' : '-' ),
    ];
  }

  usort( $result, function ( $f1, $f2 ) {
    $f1_key = ( $f1['is_dir'] ?: 2 ) . $f1['filename'];
    $f2_key = ( $f2['is_dir'] ?: 2 ) . $f2['filename'];
    return $f1_key > $f2_key;
  } );

  return $result;
}

function getDirectorySize( $path )
{
  $size = 0;
  $path = realpath( $path );
  if ( $path !== false && $path != '' && file_exists( $path ) ) {
    foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ) ) as $obj ) {
      $size += $obj->getSize();
    }
  }
  return $size;
}

function getDSN()
{
  global $sourcePath;
  $dbm           = new PDO( 'sqlite::memory:' );
  $sqliteVersion = $dbm->query( 'SELECT sqlite_version()' )->fetch()[0];
  $dbm           = null;
  if ( version_compare( $sqliteVersion, '3.7.0', '>=' ) ) {
    $dsn = sprintf( 'sqlite:%s%s%s', $sourcePath, DIRECTORY_SEPARATOR, 'structure.db' );
  } else {
    $dsn = sprintf( 'sqlite:%s%s%s', $sourcePath, DIRECTORY_SEPARATOR, 'structure.legacy.db' );
  }

  return $dsn;
}

function getBackups( $output = 'data' )
{
  global $ACMS;

  $pdo     = newPDO();
  $backups = array();

  createTable( 'backup' );

  switch ( $output ) :
    case 'data' :
      $stmt = $pdo->prepare( "SELECT rowid, * FROM backup ORDER BY rowid DESC LIMIT " . $ACMS['ACMS_MATCHES_LIMIT'] );
      $stmt->execute();
      while ( $backup = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
        $backups[$backup['rowid']] = $backup;
      }
      break;
    case 'stats' :
      $stmt = $pdo->prepare( "SELECT COUNT(1) FROM backup" );
      $stmt->execute();
      return ['total' => intval( $stmt->fetchColumn() )];
      break;
  endswitch;

  return $backups;
}

function getBackupsBreakpoints()
{
  $result = [];
  $pdo    = newPDO();
  $stmt   = $pdo->query( "SELECT rowid, settings, created FROM backup WHERE action = 'breakpoint' ORDER by rowid DESC" );
  while ( $breakpoint = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
    $result[$breakpoint['rowid']] = [
      'rowid'   => $breakpoint['rowid'],
      'name'    => json_decode( $breakpoint['settings'], true )['name'],
      'created' => $breakpoint['created'],
    ];
  }
  return $result;
}

function getBackupsByDocumentId( $documentId )
{
  $result = [];
  $i      = 0;
  $pdo    = newPDO();
  $stmt   = $pdo->prepare( "SELECT rowid, * FROM backup WHERE id = :documentId ORDER BY rowid DESC" );
  $stmt->bindParam( 'documentId', $documentId, PDO::PARAM_INT );
  $stmt->execute();

  while ( $backup = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
    $result[$i]             = $backup;
    $result[$i]['settings'] = json_decode( $backup['settings'], true );
    $i++;
  }
  return $result;
}

function getHumanSize( $bytes, $decimals = 2 )
{
  $size   = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
  $factor = floor( ( strlen( $bytes ) - 1 ) / 3 );

  return sprintf( "%.{$decimals}f", $bytes / pow( 1024, $factor ) ) . @$size[$factor];
}

function getImportInfo( $importFile )
{
  global $ACMS;

  $import['id']       = getRandomString( 8 );
  $import['zip_path'] = $importFile;
  $import['filename'] = basename( $importFile );
  $import['filesize'] = filesize( $importFile );
  $zip                = new ZipArchive();
  $res                = $zip->open( $import['zip_path'], ZipArchive::CHECKCONS );
  if ( $res !== true ) return;
  for ( $i = 0; $i < $zip->numFiles; $i++ ) {
    if ( preg_match( '~^[.]content[.][0-9a-z]+$~i', basename( $zip->statIndex( $i )['name'] ) ) && $zip->statIndex( $i )['size'] == 0 ) {
      $tmpDatabase                 = tempnam( getTempDirectory(), 'archivarix.' );
      $import['content_directory'] = basename( $zip->statIndex( $i )['name'] );
      $import['tmp_database']      = $tmpDatabase;
      $import['zip_source_path']   = $zip->statIndex( $i )['name'];
      $import['loader_settings']   = $zip->locateName( $import['zip_source_path'] . '.loader.settings.json' ) ? 1 : 0;
      $import['acms_settings']     = $zip->locateName( $import['zip_source_path'] . '.acms.settings.json' ) ? 1 : 0;
      $import['custom_includes']   = $zip->locateName( $import['zip_source_path'] . 'includes/' ) ? 1 : 0;
      file_put_contents( $tmpDatabase, $zip->getFromName( $import['zip_source_path'] . 'structure.db' ) );
      $import['info'] = getInfoFromDatabase( "sqlite:{$tmpDatabase}" );
      if ( isset( $import['info']['settings']['uuidg'] ) ) {
        $import['screenshot'] = 'https://download.archivarix.cloud/screenshots/' . $import['info']['settings']['uuidg'][0] . '/' . $import['info']['settings']['uuidg'][1] . '/' . $import['info']['settings']['uuidg'] . '_THUMB.jpg';
        $import['url']        = 'https://archivarix.com/' . $_SESSION['archivarix.lang'] . '/status/' . $import['info']['settings']['uuidg'] . '/';
      } else {
        $import['url'] = 'https://archivarix.com/' . $_SESSION['archivarix.lang'] . '/status/' . $import['info']['settings']['uuid'] . '/';
      }
      break;
    }
  }


  if ( !empty( $import['custom_includes'] ) && !inSafeMode() ) {
    $includesPath              = $import['zip_source_path'] . 'includes/';
    $includesLen               = strlen( $includesPath ) + 1;
    $import['custom_includes'] = [];
    for ( $i = 0; $i < $zip->numFiles; $i++ ) {
      if ( substr_compare( $zip->statIndex( $i )['name'], $includesPath, 0, $includesLen ) == 1 ) {
        $import['custom_includes'][$i]             = $zip->statIndex( $i );
        $import['custom_includes'][$i]['filename'] = substr( $import['custom_includes'][$i]['name'], $includesLen - 1 );
        $import['custom_includes'][$i]['is_dir']   = substr( $import['custom_includes'][$i]['name'], -1 ) == DIRECTORY_SEPARATOR ? 1 : 0;
        $import['custom_includes'][$i]['levels']   = substr_count( $import['custom_includes'][$i]['filename'], DIRECTORY_SEPARATOR );
      }
    }

    if ( !empty( $import['info']['templates'] ) ) {
      $import['templates'] = [];
      $templatesPath       = $import['zip_source_path'] . 'templates/';
      $templatesLen        = strlen( $templatesPath ) + 1;
      for ( $i = 0; $i < $zip->numFiles; $i++ ) {
        if (
          substr_compare( $zip->statIndex( $i )['name'], $templatesPath, 0, $templatesLen ) == 1 &&
          in_array( basename( $zip->statIndex( $i )['name'], ".html" ), $import['info']['templates'] )
        ) {
          $import['templates'][basename( $zip->statIndex( $i )['name'], ".html" )] = [
            'name'     => basename( $zip->statIndex( $i )['name'], ".html" ),
            'path'     => $zip->statIndex( $i )['name'],
            'filesize' => $zip->statIndex( $i )['size'],
          ];
        }
      }
    }

    usort( $import['custom_includes'], function ( $f1, $f2 ) {
      $f1_key = ( $f1['levels'] ?: 3 ) . ( $f1['is_dir'] ?: 2 ) . $f1['filename'];
      $f2_key = ( $f2['levels'] ?: 3 ) . ( $f2['is_dir'] ?: 2 ) . $f2['filename'];
      return $f1_key > $f2_key;
    } );
  }
  $zip->close();

  if ( !isset( $import['info']['settings'] ) ) return;
  return $import;
}

function getImportsList()
{
  $imports = [];
  if ( !empty( getMissingExtensions( ['zip'] ) ) ) return $imports;
  $importsPath    = createDirectory( 'imports' );
  $importZipFiles = glob( $importsPath . DIRECTORY_SEPARATOR . "*.zip" );
  usort( $importZipFiles, function ( $a, $b ) {
    return filemtime( $b ) - filemtime( $a );
  } );
  foreach ( $importZipFiles as $fileName ) {
    $importInfo = getImportInfo( $fileName );
    if ( !empty( $importInfo ) ) {
      $imports[] = $importInfo;
    }
  }

  return $imports;
}

function getInfoFromDatabase( $dsn )
{
  $info = [];
  $pdo  = new PDO( $dsn );

  $stmt = $pdo->prepare( 'SELECT hostname, COUNT(*) as count, SUM(filesize) as size, SUM(CASE WHEN redirect != "" THEN 1 ELSE 0 END) as redirects FROM structure GROUP BY hostname ORDER BY count DESC, hostname' );
  $stmt->execute();
  while ( $hostname = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
    $info['hostnames'][$hostname['hostname']] = $hostname;
  }

  $stmt = $pdo->prepare( "SELECT * FROM settings ORDER BY param" );
  $stmt->execute();
  while ( $setting = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
    $info['settings'][$setting['param']] = $setting['value'];
  }

  $stmt = $pdo->prepare( 'SELECT mimetype, COUNT(*) as count, SUM(filesize) as size FROM structure WHERE redirect = "" GROUP BY mimetype ORDER BY mimetype' );
  $stmt->execute();
  $info['filescount'] = 0;
  $info['filessize']  = 0;
  while ( $mimetype = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
    $info['mimestats'][$mimetype['mimetype']] = $mimetype;
    $info['filescount']                       += $mimetype['count'];
    $info['filessize']                        += $mimetype['size'];
  }

  $stmt = $pdo->query( "SELECT 1 FROM sqlite_master WHERE type='table' AND name='templates'" );
  if ( $stmt->fetchColumn() ) {
    $stmt = $pdo->prepare( "SELECT * FROM templates ORDER BY name" );
    $stmt->execute();
    while ( $template = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
      $info['templates'][] = $template['name'];
    }
  }

  if ( !empty( $info ) ) {
    $info['id'] = getRandomString( 8 );
  }

  return $info;
}

function getLoaderInfo()
{
  $return    = ['filename' => false, 'version' => false, 'integration' => false];
  $filenames = ['index.php', 'archivarix.php'];
  foreach ( $filenames as $filename ) {
    if ( file_exists( __DIR__ . DIRECTORY_SEPARATOR . $filename ) ) {
      $loaderContent = file_get_contents( __DIR__ . DIRECTORY_SEPARATOR . $filename );
      preg_match( '~const ARCHIVARIX_VERSION = \'([\d.]+)\'~', $loaderContent, $loaderMatches );
      if ( !empty( $loaderMatches[1] ) ) {
        $return['version']  = $loaderMatches[1];
        $return['filename'] = $filename;
      }
      if ( preg_match( '~@package[\s]+WordPress~', file_get_contents( $filename ) ) ) $return['integration'] = 'wordpress';
      elseif ( preg_match( '~@package[\s]+Joomla.Site~', file_get_contents( $filename ) ) ) $return['integration'] = 'joomla';
    }
  }

  return $return;
}

function getMetaData( $rowid )
{
  $pdo  = newPDO();
  $stmt = $pdo->prepare( 'SELECT rowid, * FROM structure WHERE rowid = :id' );
  $stmt->execute( ['id' => $rowid] );
  $metaData = $stmt->fetch( PDO::FETCH_ASSOC );
  return $metaData;
}

function getMimeByExtension( $extension )
{
  $knownMime = [
    '3g2'    => ['video/3gpp2', 'binary'],
    '3gp'    => ['video/3gpp', 'binary'],
    '7z'     => ['application/x-7z-compressed', 'binary'],
    'aac'    => ['audio/aac', 'binary'],
    'apng'   => ['image/apng', 'binary'],
    'avi'    => ['video/x-msvideo', 'binary'],
    'bmp'    => ['image/x-bmp', 'binary'],
    'css'    => ['text/css', 'html'],
    'csv'    => ['text/csv', 'html'],
    'doc'    => ['application/msword', 'binary'],
    'docx'   => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'binary'],
    'eot'    => ['application/vnd.ms-fontobject', 'binary'],
    'epub'   => ['application/epub+zip', 'binary'],
    'flac'   => ['audio/flac', 'binary'],
    'gif'    => ['image/gif', 'binary'],
    'gz'     => ['application/gzip', 'binary'],
    'heic'   => ['image/heic', 'binary'],
    'heif'   => ['image/heif', 'binary'],
    'htc'    => ['text/x-component', 'html'],
    'html'   => ['text/html', 'html'],
    'ico'    => ['image/x-icon', 'binary'],
    'ics'    => ['text/calendar', 'html'],
    'jar'    => ['application/java-archive', 'binary'],
    'jp2'    => ['image/jp2', 'binary'],
    'jpg'    => ['image/jpeg', 'binary'],
    'jpeg'   => ['image/jpeg', 'binary'],
    'jpm'    => ['image/jpm', 'binary'],
    'jpx'    => ['image/jpx', 'binary'],
    'js'     => ['application/javascript', 'html'],
    'json'   => ['application/json', 'html'],
    'jsonld' => ['application/ld+json', 'html'],
    'jxr'    => ['image/jxr', 'binary'],
    'mid'    => ['audio/midi', 'binary'],
    'mov'    => ['video/quicktime', 'binary'],
    'mp3'    => ['audio/mpeg', 'binary'],
    'mp4'    => ['video/mp4', 'binary'],
    'oga'    => ['audio/ogg', 'binary'],
    'ogv'    => ['video/ogg', 'binary'],
    'ogx'    => ['application/ogg', 'binary'],
    'opus'   => ['audio/opus', 'binary'],
    'otf'    => ['font/otf', 'binary'],
    'pdf'    => ['application/pdf', 'binary'],
    'png'    => ['image/png', 'binary'],
    'ppt'    => ['application/vnd.ms-powerpoint', 'binary'],
    'pptx'   => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'binary'],
    'rar'    => ['application/x-rar-compressed', 'binary'],
    'rtf'    => ['application/rtf', 'binary'],
    'svg'    => ['image/svg+xml', 'binary'],
    'swf'    => ['application/x-shockwave-flash', 'binary'],
    'tar'    => ['application/x-tar', 'binary'],
    'tif'    => ['image/tiff', 'binary'],
    'ts'     => ['video/mp2t', 'binary'],
    'ttf'    => ['font/ttf', 'binary'],
    'txt'    => ['text/plain', 'html'],
    'vcard'  => ['text/vcard', 'html'],
    'wav'    => ['audio/wav', 'binary'],
    'wave'   => ['audio/wave', 'binary'],
    'weba'   => ['audio/webm', 'binary'],
    'webm'   => ['video/webm', 'binary'],
    'webp'   => ['image/webp', 'binary'],
    'woff'   => ['font/woff', 'binary'],
    'woff2'  => ['font/woff2', 'binary'],
    'xbm'    => ['image/x-xbm', 'binary'],
    'xhtml'  => ['application/xhtml+xml', 'html'],
    'xls'    => ['application/vnd.ms-excel', 'binary'],
    'xlsx'   => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'binary'],
    'xml'    => ['application/xml', 'html'],
    'zip'    => ['application/zip', 'binary'],
  ];

  if ( key_exists( $extension, $knownMime ) ) return [
    'mimetype' => $knownMime[$extension][0],
    'folder'   => $knownMime[$extension][1],
  ];
  return ['mimetype' => 'application/octet-stream', 'folder' => 'binary'];
}

function getMimeInfo( $mimeName )
{
  $knownMime = [
    'application/atom+xml'                                                      => ['html', 'xml', 'fa-file-code', 'code'],
    'application/ecmascript'                                                    => ['html', 'js', 'fa-file-code', 'code'],
    'application/epub+zip'                                                      => ['binary', 'epub', 'fa-file', ''],
    'application/gzip'                                                          => ['binary', 'gz', 'fa-file-archive', 'archive'],
    'application/java-archive'                                                  => ['binary', 'jar', 'fa-file-archive', 'archive'],
    'application/javascript'                                                    => ['html', 'js', 'fa-file-code', 'code'],
    'application/json'                                                          => ['html', 'json', 'fa-file-code', 'code'],
    'application/json+oembed'                                                   => ['html', 'json', 'fa-file-code', 'code'],
    'application/ld+json'                                                       => ['html', 'jsonld', 'fa-file-code', 'code'],
    'application/msword'                                                        => ['binary', 'doc', 'fa-file-word', 'word'],
    'application/ogg'                                                           => ['binary', 'ogx', 'fa-file-audio', 'audio'],
    'application/opensearchdescription+xml'                                     => ['html', 'xml', 'fa-file-code', 'code'],
    'application/pdf'                                                           => ['binary', 'pdf', 'fa-file-pdf', 'pdf'],
    'application/php'                                                           => ['html', 'txt', 'fa-file-code', 'code'],
    'application/rdf+xml'                                                       => ['html', 'xml', 'fa-file-code', 'code'],
    'application/rss+xml'                                                       => ['html', 'xml', 'fa-file-code', 'code'],
    'application/rtf'                                                           => ['binary', 'rtf', 'fa-file', ''],
    'application/vnd.ms-excel'                                                  => ['binary', 'xls', 'fa-file-excel', 'excel'],
    'application/vnd.ms-fontobject'                                             => ['binary', 'eot', 'fa-file', ''],
    'application/vnd.ms-powerpoint'                                             => ['binary', 'ppt', 'fa-file-powerpoint', 'powerpoint'],
    'application/vnd.openxmlformats-officedocument.presentationml.presentation' => ['binary', 'pptx', 'fa-file-powerpoint', 'powerpoint'],
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'         => ['binary', 'xlsx', 'fa-file-excel', 'excel'],
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'   => ['binary', 'docx', 'fa-file-word', 'word'],
    'application/x-7z-compressed'                                               => ['binary', '7z', 'fa-file-archive', 'archive'],
    'application/x-bzpdf'                                                       => ['binary', 'pdf', 'fa-file-pdf', 'pdf'],
    'application/x-csh'                                                         => ['html', 'txt', 'fa-file-code', 'code'],
    'application/x-gzpdf'                                                       => ['binary', 'pdf', 'fa-file-pdf', 'pdf'],
    'application/x-httpd-php'                                                   => ['html', 'html', 'fa-file-code', 'code'],
    'application/x-javascript'                                                  => ['html', 'js', 'fa-file-code', 'code'],
    'application/x-pdf'                                                         => ['binary', 'pdf', 'fa-file-pdf', 'pdf'],
    'application/x-rar-compressed'                                              => ['binary', 'rar', 'fa-file-archive', 'archive'],
    'application/x-sh'                                                          => ['html', 'txt', 'fa-file-code', 'code'],
    'application/x-shockwave-flash'                                             => ['binary', 'swf', 'fa-file', ''],
    'application/x-tar'                                                         => ['binary', 'tar', 'fa-file-archive', 'archive'],
    'application/x-zip-compressed'                                              => ['binary', 'zip', 'fa-file-archive', 'archive'],
    'application/xhtml+xml'                                                     => ['html', 'xhtml', 'fa-file-code', 'code'],
    'application/xml'                                                           => ['html', 'xml', 'fa-file-code', 'code'],
    'application/zip'                                                           => ['binary', 'zip', 'fa-file-archive', 'archive'],
    'audio/3gpp'                                                                => ['binary', '3gp', 'fa-file-audio', 'audio'],
    'audio/3gpp2'                                                               => ['binary', '3g2', 'fa-file-audio', 'audio'],
    'audio/aac'                                                                 => ['binary', 'aac', 'fa-file-audio', 'audio'],
    'audio/flac'                                                                => ['binary', 'flac', 'fa-file-audio', 'audio'],
    'audio/midi'                                                                => ['binary', 'mid', 'fa-file-audio', 'audio'],
    'audio/mpeg'                                                                => ['binary', 'mp3', 'fa-file-audio', 'audio'],
    'audio/ogg'                                                                 => ['binary', 'oga', 'fa-file-audio', 'audio'],
    'audio/opus'                                                                => ['binary', 'opus', 'fa-file-audio', 'audio'],
    'audio/wav'                                                                 => ['binary', 'wav', 'fa-file-audio', 'audio'],
    'audio/wave'                                                                => ['binary', 'wav', 'fa-file-audio', 'audio'],
    'audio/webm'                                                                => ['binary', 'weba', 'fa-file-audio', 'audio'],
    'audio/x-flac'                                                              => ['binary', 'flac', 'fa-file-audio', 'audio'],
    'audio/x-pn-wav'                                                            => ['binary', 'wav', 'fa-file-audio', 'audio'],
    'audio/x-wav'                                                               => ['binary', 'wav', 'fa-file-audio', 'audio'],
    'font/otf'                                                                  => ['binary', 'otf', 'fa-file', ''],
    'font/ttf'                                                                  => ['binary', 'ttf', 'fa-file', ''],
    'font/woff'                                                                 => ['binary', 'woff', 'fa-file', ''],
    'font/woff2'                                                                => ['binary', 'woff2', 'fa-file', ''],
    'inode/x-empty'                                                             => ['html', 'txt', 'fa-file-alt', 'text'],
    'image/apng'                                                                => ['binary', 'apng', 'fa-file-image', 'image'],
    'image/gif'                                                                 => ['binary', 'gif', 'fa-file-image', 'image'],
    'image/heic'                                                                => ['binary', 'heic', 'fa-file-image', 'image'],
    'image/heic-sequence'                                                       => ['binary', 'heic', 'fa-file-image', 'image'],
    'image/heif'                                                                => ['binary', 'heif', 'fa-file-image', 'image'],
    'image/heif-sequence'                                                       => ['binary', 'heif', 'fa-file-image', 'image'],
    'image/jp2'                                                                 => ['binary', 'jp2', 'fa-file-image', 'image'],
    'image/jpeg'                                                                => ['binary', 'jpg', 'fa-file-image', 'image'],
    'image/jpg'                                                                 => ['binary', 'jpg', 'fa-file-image', 'image'],
    'image/jpm'                                                                 => ['binary', 'jpm', 'fa-file-image', 'image'],
    'image/jpx'                                                                 => ['binary', 'jpx', 'fa-file-image', 'image'],
    'image/jxr'                                                                 => ['binary', 'jxr', 'fa-file-image', 'image'],
    'image/pjpeg'                                                               => ['binary', 'jpg', 'fa-file-image', 'image'],
    'image/png'                                                                 => ['binary', 'png', 'fa-file-image', 'image'],
    'image/svg'                                                                 => ['binary', 'svg', 'fa-file-image', 'image'],
    'image/svg+xml'                                                             => ['binary', 'svg', 'fa-file-image', 'image'],
    'image/tiff'                                                                => ['binary', 'tif', 'fa-file-image', 'image'],
    'image/tiff-fx'                                                             => ['binary', 'tif', 'fa-file-image', 'image'],
    'image/vnd.ms-photo'                                                        => ['binary', 'jxr', 'fa-file-image', 'image'],
    'image/webp'                                                                => ['binary', 'webp', 'fa-file-image', 'image'],
    'image/x-bmp'                                                               => ['binary', 'bmp', 'fa-file-image', 'image'],
    'image/x-icon'                                                              => ['binary', 'ico', 'fa-file-image', 'image'],
    'image/x-xbitmap'                                                           => ['binary', 'bmp', 'fa-file-image', 'image'],
    'image/x-xbm'                                                               => ['binary', 'xbm', 'fa-file-image', 'image'],
    'text/calendar'                                                             => ['html', 'ics', 'fa-file-alt', 'text'],
    'text/css'                                                                  => ['html', 'css', 'fa-file-code', 'code'],
    'text/csv'                                                                  => ['html', 'csv', 'fa-file-alt', 'text'],
    'text/ecmascript'                                                           => ['html', 'js', 'fa-file-code', 'code'],
    'text/event-stream'                                                         => ['html', 'txt', 'fa-file-alt', 'text'],
    'text/html'                                                                 => ['html', 'html', 'fa-file-code', 'html'],
    'text/javascript'                                                           => ['html', 'js', 'fa-file-code', 'code'],
    'text/json'                                                                 => ['html', 'json', 'fa-file-code', 'code'],
    'text/pl'                                                                   => ['html', 'txt', 'fa-file-code', 'code'],
    'text/plain'                                                                => ['html', 'txt', 'fa-file-alt', 'text'],
    'text/text'                                                                 => ['html', 'txt', 'fa-file-alt', 'text'],
    'text/vbscript'                                                             => ['html', 'txt', 'fa-file-code', 'code'],
    'text/vcard'                                                                => ['html', 'vcard', 'fa-file-code', 'code'],
    'text/vnd'                                                                  => ['html', 'txt', 'fa-file-alt', 'alt'],
    'text/vnd.wap.wml'                                                          => ['html', 'txt', 'fa-file-alt', 'alt'],
    'text/x-component'                                                          => ['html', 'htc', 'fa-file-code', 'code'],
    'text/x-js'                                                                 => ['html', 'js', 'fa-file-code', 'code'],
    'text/x-php'                                                                => ['html', 'html', 'fa-file-code', 'code'],
    'text/x-vcard'                                                              => ['html', 'vcard', 'fa-file-code', 'code'],
    'text/xml'                                                                  => ['html', 'xml', 'fa-file-code', 'code'],
    'video/3gpp'                                                                => ['binary', '3gp', 'fa-file-video', 'video'],
    'video/3gpp2'                                                               => ['binary', '3g2', 'fa-file-video', 'video'],
    'video/mp2t'                                                                => ['binary', 'ts', 'fa-file-video', 'video'],
    'video/mp4'                                                                 => ['binary', 'mp4', 'fa-file-video', 'video'],
    'video/ogg'                                                                 => ['binary', 'ogv', 'fa-file-video', 'video'],
    'video/quicktime'                                                           => ['binary', 'mov', 'fa-file-video', 'video'],
    'video/webm'                                                                => ['binary', 'webm', 'fa-file-video', 'video'],
    'video/x-msvideo'                                                           => ['binary', 'avi', 'fa-file-video', 'video'],
  ];

  if ( array_key_exists( $mimeName, $knownMime ) ) {
    return [
      'folder'    => $knownMime[$mimeName][0],
      'extension' => $knownMime[$mimeName][1],
      'icon'      => $knownMime[$mimeName][2],
      'type'      => $knownMime[$mimeName][3],
    ];
  }

  return ['folder' => 'binary', 'extension' => 'data', 'icon' => 'fa-file', 'type' => ''];
}

function getMimeStats()
{
  $pdo  = newPDO();
  $stmt = $pdo->prepare( 'SELECT mimetype, COUNT(*) as count, SUM(filesize) as size FROM structure GROUP BY mimetype ORDER BY mimetype' );
  $stmt->execute();

  return $stmt->fetchAll( PDO::FETCH_ASSOC );
}

function getMissingExtensions( $extensions )
{
  return array_diff( $extensions, get_loaded_extensions() );
}

function getMissingUrls( $output = 'data' )
{
  $pdo = newPDO();

  $exists = $pdo->query( "SELECT 1 FROM sqlite_master WHERE name='missing'" )->fetchColumn();

  if ( !$exists ) return false;

  switch ( $output ) :
    case 'data' :
      $stmt = $pdo->prepare( 'SELECT rowid, * FROM missing ORDER BY url' );
      $stmt->execute();
      return $stmt->fetchAll( PDO::FETCH_ASSOC );
      break;
    case 'stats' :
      $stmt = $pdo->prepare( 'SELECT COUNT(1) AS `total`, SUM(`status`) AS visits, SUM(`ignore`) AS `ignore` FROM missing ORDER BY url' );
      $stmt->execute();
      return $stmt->fetch( PDO::FETCH_ASSOC );
      break;
  endswitch;
}

function getOnlyCustomFiles( $files )
{
  $result = [];
  foreach ( $files as $file ) {
    if ( !$file['is_dir'] ) $result[] = $file;
  }
  return $result;
}

function getPathAvailable( $path )
{
  $pdo = newPDO();

  $pathEncoded = encodePath( $path );
  $stmt        = $pdo->prepare( "SELECT rowid FROM structure WHERE request_uri = :path" );
  $stmt->bindParam( 'path', $pathEncoded );
  $stmt->execute();
  if ( !$stmt->fetchColumn() ) return $path;

  preg_match( '~(.*?)(-[\d]+)?([/.])?(' . preg_quote( pathinfo( $path, PATHINFO_EXTENSION ), '~' ) . ')$~', $path, $matches );
  $pathPreExtension = $matches[1];
  $pathSeparator    = $matches[3];
  $pathExtension    = $matches[4];
  $counter          = 0;
  while ( true ) {
    $pathNew = $pathPreExtension . ( $counter ? '-' . $counter : '' ) . $pathSeparator . $pathExtension;
    if ( substr( $pathNew, 0, 1 ) != '/' ) $pathNew = '/' . $pathNew;
    $pathEncoded = encodePath( $pathNew );
    $stmt        = $pdo->prepare( "SELECT rowid FROM structure WHERE request_uri = :path" );
    $stmt->bindParam( 'path', $pathEncoded );
    $stmt->execute();
    if ( !$stmt->fetchColumn() ) break;
    $counter++;
  }
  return $pathNew;
}

function getRandomString( $len = 32 )
{
  mt_srand();
  $getBytes = function_exists( 'random_bytes' ) ? 'random_bytes' : 'openssl_random_pseudo_bytes';
  $string   = substr( strtoupper( base_convert( bin2hex( $getBytes( $len * 4 ) ), 16, 35 ) ), 0, $len );
  for ( $i = 0, $c = strlen( $string ); $i < $c; $i++ )
    $string[$i] = ( mt_rand( 0, 1 )
      ? strtoupper( $string[$i] )
      : strtolower( $string[$i] ) );
  return $string;
}

function getSettings()
{
  $pdo  = newPDO();
  $stmt = $pdo->prepare( 'SELECT * FROM settings' );
  $stmt->execute();

  $uuidSettings = array();

  while ( $setting = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
    $uuidSettings[$setting['param']] = $setting['value'];
  }

  return $uuidSettings;
}

function getSourceRoot()
{
  $path = '';

  if ( ACMS_CONTENT_PATH && file_exists( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . ACMS_CONTENT_PATH ) ) {
    $absolutePath = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . ACMS_CONTENT_PATH;
    if ( !file_exists( $absolutePath . DIRECTORY_SEPARATOR . 'structure.db' ) || filesize( $absolutePath . DIRECTORY_SEPARATOR . 'structure.db' ) == 0 ) {
      header( 'X-Error-Description: Custom content directory is missing or empty.' );
      return false;
    } else {
      return $absolutePath;
    }
  }

  $list = scandir( dirname( __FILE__ ) );
  foreach ( $list as $item ) {
    if ( preg_match( '~^\.content\.[0-9a-zA-Z]+$~', $item ) && is_dir( $item ) ) {
      $path = $item;
      break;
    }
  }

  if ( !$path ) {
    header( 'X-Error-Description: Content directory is missing.' );
    return false;
  }

  $absolutePath = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . $path;

  if ( !realpath( $absolutePath ) ) {
    return false;
    //throw new \Exception( sprintf( 'Directory %s does not exist', $absolutePath ) );
  }

  if ( !file_exists( $absolutePath . DIRECTORY_SEPARATOR . 'structure.db' ) || filesize( $absolutePath . DIRECTORY_SEPARATOR . 'structure.db' ) == 0 ) {
    return false;
  }

  return $absolutePath;
}

function getSqliteVersion()
{
  $dbm = new PDO( 'sqlite::memory:' );
  return $dbm->query( 'SELECT sqlite_version()' )->fetch()[0];
}

function getTempDirectory()
{
  return ini_get( 'upload_tmp_dir' ) ? ini_get( 'upload_tmp_dir' ) : sys_get_temp_dir();
}

function getTemplate( $name )
{
  global $sourcePath;
  $pdo  = newPDO();
  $stmt = $pdo->prepare( "SELECT rowid, * FROM templates WHERE name = :name" );
  $stmt->bindParam( 'name', $name, PDO::PARAM_STR );
  $stmt->execute();

  $template = $stmt->fetch( PDO::FETCH_ASSOC );
  if ( !empty( $template ) ) {
    $template['content'] = file_get_contents( $sourcePath . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . $template['name'] . '.html' );
  }
  return $template;
}

function getTemplateInfo( $name )
{
  $result = ['name' => $name];
  $icons  = ['STRING' => 'fa-align-left', 'HTML' => 'fa-code', 'FILE' => 'fa-file', 'URL' => 'fa-link'];
  global $sourcePath;
  $file = $sourcePath . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . $name . '.html';
  // get params
  preg_match_all( "~\{\{@(STRING|HTML|FILE|URL)\('([-a-z0-9]+)'\)\}\}~is", file_get_contents( $file ), $matches, PREG_OFFSET_CAPTURE );
  if ( !empty( $matches[2] ) ) {
    foreach ( $matches[2] as $num => $data ) {
      $result['params'][$data[0]][] = [
        'name'     => $data[0],
        'type'     => strtoupper( $matches[1][$num][0] ),
        'string'   => $matches[0][$num][0],
        'position' => $matches[0][$num][1],
        'icon'     => $icons[strtoupper( $matches[1][$num][0] )],
      ];
    }
  } else {
    $result['params'] = [];
  }
  return $result;
}

function getTemplateNameAvailable( $name )
{
  $name = strtolower( $name );
  createTable( 'templates' );
  $pdo  = newPDO();
  $stmt = $pdo->prepare( "SELECT name FROM templates WHERE name = :name" );
  $stmt->bindParam( 'name', $name );
  $stmt->execute();
  if ( !$stmt->fetchColumn() ) return $name;

  preg_match( '~(.*?)(-[\d]+)?$~', $name, $matches );
  $namePre = $matches[1];
  $counter = 0;
  while ( true ) {
    $nameNew = $namePre . ( $counter ? '-' . $counter : '' );
    $stmt    = $pdo->prepare( "SELECT name FROM templates WHERE name = :name" );
    $stmt->bindParam( 'name', $nameNew );
    $stmt->execute();
    if ( !$stmt->fetchColumn() ) break;
    $counter++;
  }
  return $nameNew;
}

function getTemplates( $hostname = '' )
{
  $pdo = newPDO();

  createTable( 'templates' );

  if ( strlen( $hostname ) ) {
    $stmt = $pdo->prepare( "SELECT rowid, * FROM templates WHERE hostname = :hostname ORDER BY name" );
    $stmt->bindParam( "hostname", $hostname, PDO::PARAM_STR );
  } else {
    $stmt = $pdo->prepare( "SELECT rowid, * FROM templates ORDER BY name" );
  }
  $stmt->execute();
  return $stmt->fetchAll( PDO::FETCH_ASSOC );
}

function getHumanTime( $inputSeconds, $precision = 4 )
{
  $precisionN = 0;
  $days       = floor( $inputSeconds / ( 60 * 60 * 24 ) );
  $remainder  = $inputSeconds % ( 60 * 60 * 24 );
  $hours      = floor( $remainder / ( 60 * 60 ) );
  $remainder  = $remainder % ( 60 * 60 );
  $minutes    = floor( $remainder / 60 );
  $remainder  = $remainder % 60;
  $seconds    = ceil( $remainder );
  $timeParts  = [];
  $sections   = [
    'day'  => (int)$days,
    'hour' => (int)$hours,
    'min'  => (int)$minutes,
    'sec'  => (int)$seconds,
  ];
  foreach ( $sections as $name => $value ) {
    if ( $value > 0 ) {
      $timeParts[] = $value . ' ' . $name . ( $value == 1 ? '' : 's' );
      $precisionN++;
      if ( $precisionN == $precision ) break;
    }
  }
  return implode( ', ', $timeParts );
}

function getTreeLi( $url )
{
  global $documentID;

  $iconColor = "text-success";
  if ( $url['enabled'] == 0 ) {
    $iconColor = "text-danger";
  }
  if ( $url['redirect'] ) {
    $iconColor = "text-warning";
  }

  $selectedClass = null;
  if ( $url['rowid'] == $documentID ) {
    $selectedClass = " class='bg-primary'";
  }

  $url['mimeinfo'] = getMimeInfo( $url['mimetype'] );
  $url['icon']     = "far {$url['mimeinfo']['icon']} {$iconColor}";

  $data = array(
    'id'    => $url['rowid'],
    'icon'  => $url['icon'],
    'order' => 2,
  );
  return "<li data-jstree='" . json_encode( $data ) . "' {$selectedClass} id='url{$url['rowid']}'>" . htmlspecialchars( rawurldecode( $url['request_uri'] ), ENT_IGNORE ) . ( $url['redirect'] ? " -> " . htmlspecialchars( rawurldecode( $url['redirect'] ), ENT_IGNORE ) : '' );
}

function getUploadLimit()
{
  $max_upload   = getBytesFromHumanSize( ini_get( 'upload_max_filesize' ) );
  $max_post     = getBytesFromHumanSize( ini_get( 'post_max_size' ) );
  $memory_limit = getBytesFromHumanSize( ini_get( 'memory_limit' ) );
  return min( $max_upload, $max_post, $memory_limit );
}

function getUrl( $rowid )
{

  $pdo  = newPDO();
  $stmt = $pdo->prepare( "SELECT rowid, * FROM structure WHERE rowid = :rowid" );
  $stmt->execute( [
    'rowid' => $rowid,
  ] );
  $stmt->execute();
  return $stmt->fetch( PDO::FETCH_ASSOC );
}

function getUrlByPath( $hostname, $path )
{
  $pdo  = newPDO();
  $stmt = $pdo->prepare( 'SELECT rowid, * FROM structure WHERE hostname = :hostname AND request_uri = :request_uri ORDER BY filetime DESC LIMIT 1' );
  $stmt->execute( [
    'hostname'    => $hostname,
    'request_uri' => $path,
  ] );
  return $stmt->fetch( PDO::FETCH_ASSOC );
}

function importPerform( $importFileName, $importSettings, $taskOffset = 0 )
{
  global $sourcePath;
  global $taskIncomplete;
  global $taskIncompleteOffset;
  global $taskStats;
  global $uuidSettings;
  global $ACMS;

  if ( !empty( unserialize( $taskStats ) ) ) {
    $stats = unserialize( $taskStats );
  } else {
    $stats = ['pages' => 0, 'processed' => 0, 'total' => 0];
  }

  if ( !empty( $_POST['disable_history'] ) ) {
    $ACMS['ACMS_DISABLE_HISTORY'] = 1;
  }

  if ( empty( $importSettings['hostnames'] ) ) return;

  $importPath = createDirectory( 'imports' );

  $import = getImportInfo( $importPath . DIRECTORY_SEPARATOR . $importFileName );
  if ( empty( $import ) ) return;

  $zip = new ZipArchive();
  $res = $zip->open( $import['zip_path'], ZipArchive::CHECKCONS );
  if ( $res !== true ) return;

  $pdoZip          = new PDO( "sqlite:{$import['tmp_database']}" );
  $sqlHostnamesArr = [];
  foreach ( $importSettings['hostnames'] as $importHostname ) {
    $sqlHostnamesArr[] = $pdoZip->quote( $importHostname, PDO::PARAM_STR );
  }
  $sqlHostnames = implode( ', ', $sqlHostnamesArr );
  if ( empty( $stats['total'] ) ) $stats['total'] = $pdoZip->query( "SELECT COUNT(1) FROM structure" )->fetchColumn();
  $stmt = $pdoZip->prepare( "SELECT rowid, * FROM structure WHERE hostname IN ({$sqlHostnames}) AND rowid > :taskOffset ORDER BY rowid" );
  $stmt->execute( ['taskOffset' => $taskOffset] );
  while ( $url = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
    $stats['processed']++;
    if ( !empty( $importSettings['subdomain'] ) ) {
      $importSettings['subdomain'] = convertIdnToAscii( strtolower( $importSettings['subdomain'] ) );
    }
    if ( !empty( $import['info']['settings']['www'] ) ) {
      $url['hostname'] = preg_replace( '~^www[.]~', '', $url['hostname'] );
    }
    if ( !empty( $importSettings['submerge'] ) ) {
      $url['new_hostname'] = ( !empty( $uuidSettings['www'] ) ? 'www.' : '' ) .
        ( !empty( $importSettings['subdomain'] ) ? "{$importSettings['subdomain']}." : '' ) .
        $uuidSettings['domain'];
    } else {
      $url['new_hostname'] = preg_replace( '~' . preg_quote( $import['info']['settings']['domain'] ) .
          '$~', '', $url['hostname'] ) . ( !empty( $uuidSettings['www'] ) ? 'www.' : '' ) .
        ( !empty( $importSettings['subdomain'] ) ? "{$importSettings['subdomain']}." : '' ) .
        $uuidSettings['domain'];
    }
    if ( !empty( $uuidSettings['www'] ) && $uuidSettings['domain'] == $url['new_hostname'] ) {
      $url['new_hostname'] = 'www.' . $url['new_hostname'];
    }
    $url['new_url'] = ( ( !empty( $uuidSettings['https'] ) ? 'https' : 'http' ) ) . '://' . $url['new_hostname'] . $url['request_uri'];
    $existingUrl    = getUrlByPath( $url['new_hostname'], $url['request_uri'] );
    switch ( $importSettings['overwrite'] ) :
      case 'skip' :
        if ( $existingUrl ) continue 2;
        break;
      case 'newer' :
        if ( $existingUrl && $url['filetime'] < $existingUrl['filetime'] ) continue 2;
        break;
    endswitch;

    $url['tmp_file_path'] = tempnam( getTempDirectory(), 'archivarix.' );
    $url['zip_file_path'] = $import['zip_source_path'] . $url['folder'] . DIRECTORY_SEPARATOR . $url['filename'];
    file_put_contents( $url['tmp_file_path'], $zip->getFromName( $url['zip_file_path'] ) );
    $url['tmp_file_size'] = filesize( $url['tmp_file_path'] );
    $url['hostname']      = strtolower( $url['new_hostname'] );
    $url['filepath']      = $url['tmp_file_path'];
    $url['filesize']      = $url['tmp_file_size'];
    $stats['pages']++;

    if ( $existingUrl ) {
      replaceUrl( $existingUrl['rowid'], $url );
    } else {
      copyUrl( $url );
    }

    unlink( $url['tmp_file_path'] );

    if ( $ACMS['ACMS_TIMEOUT'] && ( microtime( true ) - ACMS_START_TIME ) > ( $ACMS['ACMS_TIMEOUT'] - 1 ) ) {
      $taskStats            = serialize( $stats );
      $taskIncomplete       = true;
      $taskIncompleteOffset = $url['rowid'];
      return $stats;
    }
  }

  if ( !empty( $importSettings['acms_settings'] ) && !inSafeMode() ) file_put_contents( $sourcePath . DIRECTORY_SEPARATOR . '.acms.settings.json', $zip->getFromName( $import['zip_source_path'] . '.acms.settings.json' ) );
  if ( !empty( $importSettings['loader_settings'] ) && !inSafeMode() ) file_put_contents( $sourcePath . DIRECTORY_SEPARATOR . '.loader.settings.json', $zip->getFromName( $import['zip_source_path'] . '.loader.settings.json' ) );
  if ( !empty( $importSettings['custom_includes'] ) && !inSafeMode() ) {
    $includesPath = createDirectory( 'includes' );
    $zip->extractTo( $includesPath . DIRECTORY_SEPARATOR, array_column( $import['custom_includes'], 'name' ) );
    copyRecursive( $includesPath . DIRECTORY_SEPARATOR . $import['zip_source_path'] . 'includes', $includesPath );
    deleteDirectory( $includesPath . DIRECTORY_SEPARATOR . $import['zip_source_path'] );
  }
  if ( !empty( $importSettings['templates'] ) ) {
    $stmt = $pdoZip->query( "SELECT * FROM templates ORDER BY name" );
    $stmt->execute();
    $templatesPath = createDirectory( 'templates' );
    while ( $template = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
      if ( array_key_exists( $template['name'], $import['templates'] ) ) {
        $templateName     = $template['name'];
        $template['name'] = getTemplateNameAvailable( $template['name'] );
        createTemplateRecord( $template );
        file_put_contents( $templatesPath . DIRECTORY_SEPARATOR . $template['name'] . ".html", $zip->getFromName( $import['templates'][$templateName]['path'] ) );
      }
    }
  }

  if ( !empty( $importSettings['integration'] ) ) {
    $LOADER = loadLoaderSettings();
    if ( $LOADER['ARCHIVARIX_LOADER_MODE'] == 0 ) {
      $LOADER['ARCHIVARIX_LOADER_MODE'] = 2;
      setLoaderSettings( $LOADER );
    }
  }

  createBackupBreakpoint( L( 'Websites import' ) . '. ' . $importFileName . '. ' . sprintf( L( 'Processed: %s' ), number_format( $stats['processed'], 0 ) ) );
  return true;
}

function L( $phrase )
{
  if ( isset( $GLOBALS['L'][$phrase] ) ) {
    return $GLOBALS['L'][$phrase];
  } else {
    return $phrase;
  }
}

function loadAcmsSettings( $filename = null )
{
  global $sourcePath;
  global $ACMS;
  if ( empty( $filename ) ) {
    $filename = $sourcePath . DIRECTORY_SEPARATOR . '.acms.settings.json';
  }
  if ( !file_exists( $filename ) ) {
    if ( !empty( $sourcePath ) && $sourcePath != __DIR__ . DIRECTORY_SEPARATOR . '.content.tmp' ) setAcmsSettings( $ACMS );
    return;
  }
  $data = json_decode( file_get_contents( $filename ), true );
  if ( json_last_error() !== JSON_ERROR_NONE ) return;
  if ( !is_array( $data ) ) return;
  $ACMS = array_merge( $ACMS, $data );
  $ACMS = array_filter( $ACMS, function ( $k ) {
    return preg_match( '~^ACMS_~i', $k );
  }, ARRAY_FILTER_USE_KEY );
  return $ACMS;
}

function loadLoaderSettings( $filename = null )
{
  global $sourcePath;
  $LOADER = [
    'ARCHIVARIX_LOADER_MODE'           => 0,
    'ARCHIVARIX_PROTOCOL'              => 'any',
    'ARCHIVARIX_INCLUDE_CUSTOM'        => [],
    'ARCHIVARIX_FIX_MISSING_IMAGES'    => 1,
    'ARCHIVARIX_FIX_MISSING_CSS'       => 1,
    'ARCHIVARIX_FIX_MISSING_JS'        => 1,
    'ARCHIVARIX_FIX_MISSING_ICO'       => 1,
    'ARCHIVARIX_REDIRECT_MISSING_HTML' => '/',
    'ARCHIVARIX_CACHE_CONTROL_MAX_AGE' => 2592000,
    'ARCHIVARIX_CONTENT_PATH'          => '',
    'ARCHIVARIX_CUSTOM_DOMAIN'         => '',
    'ARCHIVARIX_SITEMAP_PATH'          => '',
    'ARCHIVARIX_CATCH_MISSING'         => '',
  ];
  if ( empty( $filename ) ) {
    $filename = $sourcePath . DIRECTORY_SEPARATOR . '.loader.settings.json';
  }
  if ( !file_exists( $filename ) ) return $LOADER;
  $data = json_decode( file_get_contents( $filename ), true );
  if ( json_last_error() !== JSON_ERROR_NONE ) return $LOADER;
  if ( !is_array( $data ) ) return $LOADER;

  $LOADER = array_merge( $LOADER, $data );
  $LOADER = array_filter( $LOADER, function ( $k ) {
    return preg_match( '~^ARCHIVARIX_~i', $k );
  }, ARRAY_FILTER_USE_KEY );
  return $LOADER;
}

function loadLocalization( $languageCode )
{
  $localization = array(
    'ru' => array(
      '"word" characters (a-z 0-9 _)'                                                                                                             => 'символ "слова" (a-z 0-9 _)',
      '%d rows selected'                                                                                                                          => 'Выбрано записей: %d',
      '%s could not be detected. Please update manually.'                                                                                         => '%s не удалось обнаружить. Пожалуйста, обновите вручную.',
      '%s free disk space left'                                                                                                                   => 'Осталось %s свободного места',
      '%s updated from version %s to %s. Click on the menu logo to reload page into the new version.'                                             => '%s обновлен с версии %s на %s. Нажмите на логотип в меню, чтобы перезагрузить старницу в новую версию.',
      '%s updated from version %s to %s.'                                                                                                         => '%s обновлен с версии %s на %s',
      '(filtered from _MAX_ total entries)'                                                                                                       => '(отфильтровано из _MAX_ записей)',
      '.htaccess editor'                                                                                                                          => 'Редактор .htaccess',
      '301-redirect for all missing pages to save backlink juice.'                                                                                => '301-перенаправление для всех отсутствующих страниц, чтобы не терять вес с бэклинков.',
      ': activate to sort column ascending'                                                                                                       => ': активировать для сортировки столбца по возрастанию',
      ': activate to sort column descending'                                                                                                      => ': активировать для сортировки столбца по убыванию',
      'A preview for this file type may not be available in browser.'                                                                             => 'Просмотр для этого типа файла может быть недоступен в браузере.',
      'API key removed.'                                                                                                                          => 'Ключ API удалён.',
      'API key'                                                                                                                                   => 'Ключ API',
      'API management'                                                                                                                            => 'Управление API',
      'API token is not working.'                                                                                                                 => 'API токен не работает.',
      'API token removed.'                                                                                                                        => 'API токен удалён.',
      'Access successfully saved.'                                                                                                                => 'Доступ успешно сохранён.',
      'Action'                                                                                                                                    => 'Действие',
      'Actions'                                                                                                                                   => 'Действия',
      'Add new rule'                                                                                                                              => 'Добавить новое правило',
      'Add the viewport meta tag to all pages of the site to adapt the site to display on mobile devices.'                                        => 'Добавить метатег viewport во все страинцы сайта для адаптации сайта к показу на мобильных устройствах.',
      'Add viewport'                                                                                                                              => 'Добавить viewport',
      'Additional parameters'                                                                                                                     => 'Дополнительные параметры',
      'Additionally check files on this server'                                                                                                   => 'Дополнительно проверять файлы на этом сервере',
      'Advanced filtering'                                                                                                                        => 'Расширенная фильтрация',
      'After the keyphrase'                                                                                                                       => 'После искомой фразы',
      'After'                                                                                                                                     => 'После',
      'All replaces have been written to files!'                                                                                                  => 'Все замены были сохранены в файлы!',
      'Allow website in robots.txt'                                                                                                               => 'Открыть сайт в robots.txt',
      'Anchors'                                                                                                                                   => 'Якоря',
      'Archivarix Loader'                                                                                                                         => 'Archivarix Лоадер',
      'Attention! Any file inside \'includes\' directory can have executable php source code. Do not import files from untrusted sources.'        => 'Внимание! Любой файл внутри папки \'includes\' может содержать исполняемый php-код. Не импортируйте файлы из ненадёжных источников.',
      'Attention! Do not close the browser window and do not stop loading, the page will reload itself.'                                          => 'Внимание! Не закрывайте окно браузера и не останавливайте загрузку, страница сама будет перезагружаться.',
      'Attention! If you do not select the checkbox, then index.php file of %s will be overwritten with Archivarix Loader.'                       => 'Внимание! Если вы не выберите флажок, то файл index.php от %s будет перезаписан Лоадером Archivarix.',
      'Attention! Only %d matches are shown from %d due to matches display limit'                                                                 => 'Внимание! Показано только %d совпадений из %d из-за лимита вывода совпадений',
      'Attention! Reinstall will delete and replace all your website data urls and its content.'                                                  => 'Внимание! Переустановка удалит и заменит все URL сайта и их содержимое.',
      'Attention! Your .content.xxxxxx directory contains extra files that do not belong there!'                                                  => 'Внимание! Ваша папка .content.xxxxxx содержит лишние файлы, которые там не должны находиться!',
      'Backups are disabled in CMS settings.'                                                                                                     => 'Создание резервных копий отключено в параметрах CMS.',
      'Base Character Classes'                                                                                                                    => 'Символьные классы',
      'Be careful as invalid syntax would result in an internal server error.'                                                                    => 'Будьте осторожны, так как неверный синтаксис может привести к внутренней ошибке сервера.',
      'Be careful as your IP may change and you will restrict yourself out. Enter IP addresses or CIDR separated by commas.'                      => 'Будьте осторожны, т.к. ваш IP может меняться и вы запретите сами себя. Вводите IP адреса или CIDR через запятую.',
      'Before the keyphrase'                                                                                                                      => 'Перед искомой фразой',
      'Before'                                                                                                                                    => 'Перед',
      'Binary'                                                                                                                                    => 'Бинарный',
      'Broken URLencoded links'                                                                                                                   => 'Битые URLencode ссылки',
      'CMS and Loader settings that were set using the Settings menu will not be affected.'                                                       => 'Настройки CMS и Лоадера, внесенные через меню настроек, сохраняются.',
      'CMS version'                                                                                                                               => 'Версия CMS',
      'CODE Replaces'                                                                                                                             => 'Замены в Коде',
      'Cancel'                                                                                                                                    => 'Отмена',
      'Charset'                                                                                                                                   => 'Кодировка',
      'Check again'                                                                                                                               => 'Проверить снова',
      'Check for updates'                                                                                                                         => 'Проверить обновления',
      'Choose JSON file with settings'                                                                                                            => 'Выберите JSON файл с настройками',
      'Choose ZIP file'                                                                                                                           => 'Выберите ZIP файл',
      'Choose file'                                                                                                                               => 'Выберите файл',
      'Choose the breakpoint'                                                                                                                     => 'Выберите точку отката',
      'Choose the import'                                                                                                                         => 'Выберите импорт',
      'Clear all'                                                                                                                                 => 'Очистить всё',
      'Clone URL'                                                                                                                                 => 'Клонировать URL',
      'Clone page'                                                                                                                                => 'Клонировать',
      'Clone'                                                                                                                                     => 'Клонировать',
      'Close'                                                                                                                                     => 'Закрыть',
      'Code / Text'                                                                                                                               => 'Код / Текст',
      'Code'                                                                                                                                      => 'Исходный код',
      'Code/text has to contain'                                                                                                                  => 'Код/текст должен содержать',
      'Complete URL path'                                                                                                                         => 'Полный путь URL',
      'Complete'                                                                                                                                  => 'Завершено',
      'Confirm action'                                                                                                                            => 'Подтвердите действие',
      'Confirm all replaces'                                                                                                                      => 'Подтвердить все замены',
      'Confirm'                                                                                                                                   => 'Подтвердить',
      'Contains'                                                                                                                                  => 'Содержит',
      'Contains: %d files, %s of data'                                                                                                            => 'Содержит: %d файлов, в объёме %s',
      'Content directory name'                                                                                                                    => 'Название папки с контентом',
      'Content'                                                                                                                                   => 'Содержимое',
      'Conversion of %d files to %s is complete.'                                                                                                 => 'Конвертация %d файлов в %s завершена.',
      'Convert to'                                                                                                                                => 'Конвертировать в',
      'Copied to clipboard'                                                                                                                       => 'Скопировано в буфер обмена',
      'Could not apply the file.'                                                                                                                 => 'Не удалось применить файл.',
      'Could not connect to the update server.'                                                                                                   => 'Не удалось подключиться к серверу обновлений.',
      'Could not create file %s.'                                                                                                                 => 'Не удалось создать файл %s.',
      'Could not create template %s.'                                                                                                             => 'Не удалось создать шаблон %s.',
      'Could not delete file %s.'                                                                                                                 => 'Не удалось удалить файл %s.',
      'Could not download restore file.'                                                                                                          => 'Не удалось скачать файл восстановления.',
      'Could not purge cache.'                                                                                                                    => 'Не удалось очистить кэш.',
      'Could not remove API key.'                                                                                                                 => 'Не удалось удалить ключ API.',
      'Could not remove the template %s.'                                                                                                         => 'Не удалось удалить шаблон %s.',
      'Could not update %s. Please update manually.'                                                                                              => 'Не удалось обновить %s. Пожалуйста, обновите вручную.',
      'Could not update file %s.'                                                                                                                 => 'Не удалось обновить файл %s.',
      'Count'                                                                                                                                     => 'Количество',
      'Create new URL'                                                                                                                            => 'Создать новый URL',
      'Create page'                                                                                                                               => 'Создать страницу',
      'Create template'                                                                                                                           => 'Создать шаблон',
      'Create'                                                                                                                                    => 'Создать',
      'Create/overwrite robots.txt and allow crawling the whole website.'                                                                         => 'Создать/перезаписать robots.txt для индексирования всего сайта.',
      'Created'                                                                                                                                   => 'Создано',
      'Current backups take %s of space. Don\'t forget to disable or purge all backups if you don\'t need them.'                                  => 'Текущие бэкапы занимают %s места. Не забывайте отключать или удалять их в случае ненадобности.',
      'Current password is hardcoded in the source-code. Password settings below will not affect hardcoded password.'                             => 'Текущий пароль зашит в исходный-код. Настройки пароля ниже не повлияют на зашитый пароль.',
      'Current safe mode password is also hardcoded in the source-code. Password settings below will not affect hardcoded password.'              => 'Текущий пароль для безопасного режима зашит в исходный-код. Настройки пароля ниже не повлияют на зашитый пароль.',
      'Custom Files'                                                                                                                              => 'Кастомные Файлы',
      'Custom domain'                                                                                                                             => 'Кастомный домен',
      'Custom viewport value'                                                                                                                     => 'Другое значение viewport',
      'Data export/import'                                                                                                                        => 'Экспорт/импорт данных',
      'Date/time'                                                                                                                                 => 'Дата/время',
      'Debug'                                                                                                                                     => 'Отладка',
      'Delete API key'                                                                                                                            => 'Удалить ключ API',
      'Delete'                                                                                                                                    => 'Удалить',
      'Development Mode setting is updated.'                                                                                                      => 'Настройки Development Mode обновлены.',
      'Disable history'                                                                                                                           => 'Запретить историю',
      'Do not close the browser window!'                                                                                                          => 'Не закрывайте окно браузера!',
      'Do not overwrite existing urls'                                                                                                            => 'Не перезаписывать URL',
      'Do not share your API key with anyone.'                                                                                                    => 'Никому не сообщайте ваш ключ API',
      'Does not contain'                                                                                                                          => 'Не содержит',
      'Domain'                                                                                                                                    => 'Домен',
      'Done.'                                                                                                                                     => 'Готово.',
      'Download File'                                                                                                                             => 'Скачать файл',
      'Download'                                                                                                                                  => 'Скачать',
      'Drag to sort'                                                                                                                              => 'Потяните для сортировки',
      'Drop file here to replace.'                                                                                                                => 'Перекиньте файл, чтобы заменить.',
      'Drop file here to upload.'                                                                                                                 => 'Перекиньте файл, чтобы загрузить.',
      'Edit .htaccess'                                                                                                                            => 'Редактировать .htaccess',
      'Edit page in external window'                                                                                                              => 'Редактировать страницу в новом окне',
      'Empty'                                                                                                                                     => 'Пусто',
      'Enable this URL'                                                                                                                           => 'Включить этот URL',
      'Enabled'                                                                                                                                   => 'Включен',
      'Enter a MIME-type'                                                                                                                         => 'Введите MIME-тип',
      'Enter a charset if required'                                                                                                               => 'Введите кодировку, если необходимо',
      'Enter a domain name'                                                                                                                       => 'Введите название домена',
      'Enter a name or a description'                                                                                                             => 'Введите название или описание',
      'Enter a name or a short description that you can understand later.'                                                                        => 'Введите название или понятное короткое описание.',
      'Enter a new path starting with a slash. This field cannot be empty.'                                                                       => 'Введите новый путь, начинающийся со слеша. Это поле не может быть пустым.',
      'Enter a password to set or leave empty to keep an existing password.'                                                                      => 'Введите пароль или оставьте пустым, если менять не нужно.',
      'Enter a path (i.e. /sitemap.xml) to response with up-to-date sitemap.'                                                                     => 'Введите путь (напр. /sitemap.xml), по которому будет отдаваться актуальная xml-карта сайта.',
      'Enter a path, e.g. /page.html'                                                                                                             => 'Введите путь, например, /page.html',
      'Enter filename'                                                                                                                            => 'Введите имя файла',
      'Enter serial number'                                                                                                                       => 'Введите серийный номер',
      'Expand/Collapse all'                                                                                                                       => 'Развернуть/Свернуть все',
      'Export website to a flat-file structure'                                                                                                   => 'Экспорт сайта в статическую структуру',
      'Export'                                                                                                                                    => 'Экспортировать',
      'Extra files or directories found: %s'                                                                                                      => 'Лишние найденные файлы или папки: %s',
      'File %s created successfully.'                                                                                                             => 'Файл %s успешно создан.',
      'File %s deleted.'                                                                                                                          => 'Файл %s удалён.',
      'File %s updated successfully.'                                                                                                             => 'Файл  %s успешно обновлён.',
      'File size'                                                                                                                                 => 'Размер файла',
      'Filename'                                                                                                                                  => 'Имя файла',
      'Files has to be placed into .content.xxxxxx/includes/ directory.'                                                                          => 'Файлы необходимо поместить в папку .content.xxxxxx/includes/',
      'Files'                                                                                                                                     => 'Файлы',
      'Filetime'                                                                                                                                  => 'Время файла',
      'Filter rule'                                                                                                                               => 'Правило фильтрации',
      'Filter'                                                                                                                                    => 'Фильтрация',
      'First'                                                                                                                                     => 'Первая',
      'Fix URL encoding'                                                                                                                          => 'Исправить URL encoding',
      'Fix missing .css'                                                                                                                          => 'Исправить отсутстсующие .css',
      'Fix missing .ico'                                                                                                                          => 'Исправить отсутствующие .ico',
      'Fix missing .js'                                                                                                                           => 'Исправить отсутствующие .js',
      'Fix missing images'                                                                                                                        => 'Исправить отсутствующие изображения',
      'Fix robots.txt'                                                                                                                            => 'Исправить robots.txt',
      'Fix versions in URLs'                                                                                                                      => 'Исправить версии в URL',
      'Fixed %d URLs, performed %d replaces in %d different pages.'                                                                               => 'Исправлено %d URL, произведено %d замен в %d страницах.',
      'Flat-File version'                                                                                                                         => 'Статическая версия',
      'Found parameters'                                                                                                                          => 'Найденные параметры',
      'From'                                                                                                                                      => 'От',
      'Gather missing requests'                                                                                                                   => 'Собирать запросы на несуществующие URL',
      'Generate API key'                                                                                                                          => 'Сгенерировать ключ API',
      'HTTP and HTTPS (default)'                                                                                                                  => 'HTTP и HTTPS (по-умолчанию)',
      'History size'                                                                                                                              => 'Размер истории',
      'History'                                                                                                                                   => 'История',
      'Hostname'                                                                                                                                  => 'Хост',
      'Hostnames and URLs count / size'                                                                                                           => 'Хосты и кол-во/размер хостов',
      'Hostnames'                                                                                                                                 => 'Хосты',
      'If settings had a password you will see a login form on the next click.'                                                                   => 'Если настройки содержали пароль, то вы увидите форму входа на следующем клике.',
      'If the import contained a settings file with a password, then you will see a login form on the next click.'                                => 'Если импорт содержал файл настроек с паролём, то вы увидите форму входа на следующем клике.',
      'If you manually edited the source code of those two files, all changes will be lost.'                                                      => 'Если вы вручную вносили изменения в их исходный код, то эти изменения будут потеряны.',
      'Ignored'                                                                                                                                   => 'Пропущен',
      'Import Archivarix CMS settings'                                                                                                            => 'Импортировать настройки Archivarix CMS',
      'Import Archivarix Loader settings'                                                                                                         => 'Импортировать настройки Archivarix Лоадера',
      'Import completed successfully.'                                                                                                            => 'Импорт успешно выполнен.',
      'Import detected templates (%d)'                                                                                                            => 'Импортировать найденные шаблоны (%d шт.)',
      'Import everything to a subdomain'                                                                                                          => 'Импортировать всё на поддомен',
      'Import files from custom \'includes\' directory'                                                                                           => 'Импортировать файлы из кастомной папки \'includes\'',
      'Import tool'                                                                                                                               => 'Инструмент импорта',
      'Import websites created by Archivarix.'                                                                                                    => 'Импортируйте сайты, созданные Archivarix.',
      'Import'                                                                                                                                    => 'Импорт',
      'In a safe mode you cannot create/edit custom files with a php code or do anything to the server.'                                          => 'В безопасном режме вы не сможете создавать/редактировать кастомные файлы с исполняемым php кодом или нанести какой-либо вред серверу.',
      'In development'                                                                                                                            => 'В разработке',
      'In order to continue, you must have the following PHP extensions enabled: %s'                                                              => 'Для того, чтобы продолжить, необходио включить следующие PHP расширения: %s',
      'Include sitemap.xml'                                                                                                                       => 'Включить sitemap.xml',
      'Including text files (js, css, txt, json, xml)'                                                                                            => 'Включая текстовые файлы (js, css, txt, json, xml)',
      'Information'                                                                                                                               => 'Информация',
      'Initial installation'                                                                                                                      => 'Начальная установка',
      'Insert'                                                                                                                                    => 'Вставить',
      'Installation can only work with SQLite version 3.7.0 or newer. Your pdo_sqlite uses version %s that is very outdated.'                     => 'Установка может работать только с SQLite версии 3.7.0 или выше. Ваш pdo_sqlite использует версию %s, которая очень сильно устарела.',
      'Integrate with the found installation of %s'                                                                                               => 'Интегрировать с найденной установкой %s',
      'Integration with a 3th party CMS, main page other system'                                                                                  => 'Интеграция со сторонней CMS, на главной другая система',
      'Integration with a 3th party CMS, main page this website'                                                                                  => 'Интеграция со сторонней CMS, на главной этот сайт',
      'It belongs to the "root" level user. Please fix directory/files owner and group, not permissions.'                                         => 'Она принадлежит пользователю уровня администратора (root). Пожалуйста, исправьте владельца/группу папки, а не права на запись.',
      'Language'                                                                                                                                  => 'Язык',
      'Last'                                                                                                                                      => 'Последняя',
      'Leave empty for a normal import.'                                                                                                          => 'Не заполняйте для обычного импорта',
      'Leave empty for no change'                                                                                                                 => 'Оставьте пустым чтобы пропустить',
      'Leave empty in most cases.'                                                                                                                => 'В большинстве случаев оставьте пустым.',
      'Leave empty to create an empty file.'                                                                                                      => 'Оставьте пустым, чтобы создать пустой файл.',
      'Leverage browser caching in seconds for static file types. To disable set 0.'                                                              => 'Поднять время для кэширование статических файлов. Чтобы отключить кэширование, поставьте 0.',
      'Limit URLs menu'                                                                                                                           => 'Ограничить меню списка URL',
      'Limit the number of results in Search & Replace. It does not affect replacing process.'                                                    => 'Ограничить количество отображаемых результатов в Поиске & Замене. На процесс замены этот параметр не влияет.',
      'Loader mode'                                                                                                                               => 'Режим лоадера',
      'Loading'                                                                                                                                   => 'Загрузка',
      'Log out'                                                                                                                                   => 'Выйти',
      'Looks like this option is not enabled in Loader\'s Settings.'                                                                              => 'Похоже, что это параметр не включён в Настройках Лоадера.',
      'MIME-type has to contain'                                                                                                                  => 'MIME-тип должен содержать',
      'MIME-type'                                                                                                                                 => 'MIME-тип',
      'MIME-types and quantity'                                                                                                                   => 'MIME-типы файлов и количество',
      'MIME-types and sizes'                                                                                                                      => 'MIME-типы файлов и размеры',
      'Make sure the protocol and the domain name are correct.'                                                                                   => 'Проверьте, что протокол и название домена указаны правильно.',
      'Max PHP memory'                                                                                                                            => 'Макс. память PHP',
      'Max upload size'                                                                                                                           => 'Макс. размер загрузки',
      'Max-age for static files'                                                                                                                  => 'Время кэширования статических файлов',
      'Maximum inserts/replacements'                                                                                                              => 'Максимальное кол-во вставок/замен',
      'Merge all URLs from subdomains to the main domain'                                                                                         => 'Склеить URL со всех поддоменов в основной домен',
      'Missing URLs'                                                                                                                              => 'Отсутствующие URL',
      'Modified'                                                                                                                                  => 'Изменено',
      'Name'                                                                                                                                      => 'Название',
      'New API key is set.'                                                                                                                       => 'Новый ключ API установлен.',
      'New breakpoint'                                                                                                                            => 'Точка отката',
      'New page based on <i>%s</i> template'                                                                                                      => 'Новая страница на основе шаблона %s',
      'New settings applied.'                                                                                                                     => 'Новые настройки применены.',
      'Newer version of URL has priority'                                                                                                         => 'Приоритет имеет более новый URL',
      'Next'                                                                                                                                      => 'Следующая',
      'No backups found.'                                                                                                                         => 'Резервных копий нет.',
      'No data available in table'                                                                                                                => 'В таблице отсутствуют данные',
      'No matches found'                                                                                                                          => 'Совпадения не найдены.',
      'No matching records found'                                                                                                                 => 'Записи отсутствуют.',
      'No missing URLs were caught during visitors website browsing.'                                                                             => 'Отсутствующих URL не было обнаружено во время просмотров веб-сайта посетителями.',
      'No'                                                                                                                                        => 'Нет',
      'None'                                                                                                                                      => 'Нет',
      'Not detected'                                                                                                                              => 'Не обнаружено',
      'Online tutorials'                                                                                                                          => 'Онлайн инструкции',
      'Open URL in external window'                                                                                                               => 'Открыть URL в новом окне',
      'Open URL'                                                                                                                                  => 'Открыть URL',
      'Other'                                                                                                                                     => 'Другое',
      'Overwrite all urls'                                                                                                                        => 'Перезаписать все URL',
      'Overwrite existing URLs only of imported version is newer'                                                                                 => 'Перезаписать существующие URL только если версия импорта новее',
      'Overwrite if already presents'                                                                                                             => 'Заменить если уже существует',
      'PHP Extension mbstring is missing. It is required for working with different charsets.'                                                    => 'На вашем сервере не установлено расширение mbstring для PHP. Оно необходимо для правильной работы с кодировками.',
      'PHP extensions'                                                                                                                            => 'PHP расширения',
      'PHP version'                                                                                                                               => 'Версия PHP',
      'Page path'                                                                                                                                 => 'Пусть страницы',
      'Pages found: %d; total matches: %d'                                                                                                        => 'Найдено страниц: %d; суммарно вхождений: %d',
      'Pagination is on. You may increase the limit in Settings at the risk of running out of RAM. The current limit per page is '                => 'Включена пагинация. Вы можете повысить лимит в Настройках, но есть риск, что может не хватить оперативной памяти. Текущее значение лимита на страницу: ',
      'Parameter'                                                                                                                                 => 'Параметр',
      'Password'                                                                                                                                  => 'Пароль',
      'Path'                                                                                                                                      => 'Путь',
      'Patterns and classes'                                                                                                                      => 'Группы и классы',
      'Permissions'                                                                                                                               => 'Права',
      'Please upload files to your hosting or use the form below to import/upload an existing restore.'                                           => 'Пожалуйста, загрузите файлы на ваш хостинг или используйте форму ниже для импорта/загрузки существующего восстановления.',
      'Position'                                                                                                                                  => 'Позиция',
      'Preview is not available because the URL is disabled or redirect is set.'                                                                  => 'Просмотр не доступен, потому что URL отключен или установлено перенаправление.',
      'Previous execution'                                                                                                                        => 'Предыдущее выполнение',
      'Previous'                                                                                                                                  => 'Предыдущая',
      'Processed: %s'                                                                                                                             => 'Обработано: %s',
      'Processing'                                                                                                                                => 'Идёт обработка',
      'Protocol'                                                                                                                                  => 'Протокол',
      'Purge Cloudflare cache using API access.'                                                                                                  => 'Очистить кэш Cloudflare через API доступ.',
      'Purge all'                                                                                                                                 => 'Удалить все',
      'Purge cache'                                                                                                                               => 'Очистить кэш',
      'Purge history up to'                                                                                                                       => 'Удалить историю до',
      'Purge selected'                                                                                                                            => 'Удалить выбранное',
      'Quantifiers'                                                                                                                               => 'Кванторы',
      'Ranges'                                                                                                                                    => 'Диапазоны',
      'Read instructions'                                                                                                                         => 'Читать инструкции',
      'Recommended time is 30 seconds.'                                                                                                           => 'Рекомендуемое время: 30 секунд.',
      'Redirect missing pages'                                                                                                                    => 'Перенаправить отсутствующие страницы',
      'Redirect'                                                                                                                                  => 'Перенаправление',
      'Redirects'                                                                                                                                 => 'Перенаправления',
      'Regex Cheat Sheet'                                                                                                                         => 'Подсказки по Регулярным выражениям',
      'Regular expression'                                                                                                                        => 'Регулярное выражение',
      'Reinstall'                                                                                                                                 => 'Переустановка',
      'Reissue API key'                                                                                                                           => 'Сбросить ключ API',
      'Reissue or remove API key that can be used for editing your website remotely.'                                                             => 'Сбросить или удалить ключ API, который возможно использовать для удаленного редактированния вашего сайта.',
      'Reissue or remove'                                                                                                                         => 'Сбросить или удалить',
      'Remove URL'                                                                                                                                => 'Удалить URL',
      'Remove all found URLs'                                                                                                                     => 'Удалить все найденные URL',
      'Remove broken images'                                                                                                                      => 'Удаление битых изображений',
      'Remove broken links'                                                                                                                       => 'Удаление битых ссылок',
      'Remove current password'                                                                                                                   => 'Удалить текущий пароль',
      'Remove current safe mode password'                                                                                                         => 'Удалить текущий пароль для безопасного режима',
      'Remove external links'                                                                                                                     => 'Удалить внешние ссылки',
      'Remove images'                                                                                                                             => 'Удалить изображения',
      'Remove links'                                                                                                                              => 'Удалить ссылки',
      'Remove template'                                                                                                                           => 'Удалить шаблон',
      'Remove token'                                                                                                                              => 'Удалить токен',
      'Removed %d broken internal images in %d different pages.'                                                                                  => 'Удалено %d битых внутренних изображений в %d страницах.',
      'Removed %d broken internal links in %d different pages.'                                                                                   => 'Удалено %d битых внутренних ссылок в %d страницах.',
      'Removed %d external links in %d different pages.'                                                                                          => 'Удалено %d внешних ссылок в %d страницах.',
      'Replace if the same URL already exists and replace version is newer'                                                                       => 'Заменить если такой URL уже существует и версия замены новее',
      'Replace is not possible. Invalid new URL.'                                                                                                 => 'Замена невозможна. Неправильный новый URL.',
      'Replace the keyphrase'                                                                                                                     => 'Вместо искомой фразы',
      'Replace with'                                                                                                                              => 'Заменить на',
      'Replace'                                                                                                                                   => 'Вместо',
      'Request check'                                                                                                                             => 'Проверка запроса',
      'Restore file %s downloaded.'                                                                                                               => 'Файл восстановления %s скачан.',
      'Restore file %s removed.'                                                                                                                  => 'Файл восстановления %s удалён.',
      'Restore info'                                                                                                                              => 'Информация о восстановлении',
      'Restore version'                                                                                                                           => 'Версия сборки',
      'Restrict by IP'                                                                                                                            => 'Ограничить по IP',
      'Results in Search & Replace'                                                                                                               => 'Результаты Поиска & Замены',
      'Roll back all'                                                                                                                             => 'Откатить все',
      'Roll back selected'                                                                                                                        => 'Откатить выбранное',
      'Roll back to a breakpoint'                                                                                                                 => 'Откатить до точки',
      'Roll back'                                                                                                                                 => 'Откатить',
      'Rollback to'                                                                                                                               => 'Откатить до',
      'Rules for insert/replace of custom files and scripts'                                                                                      => 'Правила вставки/замены кастомных файлов и скриптов',
      'Run import'                                                                                                                                => 'Запустить импорт',
      'SQLite version'                                                                                                                            => 'Версия SQLite',
      'Safe mode is On'                                                                                                                           => 'Безопасный режим',
      'Safe mode password'                                                                                                                        => 'Пароль для безопасного режима',
      'Save settings only'                                                                                                                        => 'Сохранить только параметры',
      'Save'                                                                                                                                      => 'Сохранить',
      'Saved'                                                                                                                                     => 'Сохранено',
      'Scan for broken internal links'                                                                                                            => 'Сканировать битые внутренние ссылки',
      'Search & Replace'                                                                                                                          => 'Поиск и Замена',
      'Search for a keyphrase'                                                                                                                    => 'Искать фразу',
      'Search for code/text'                                                                                                                      => 'Искать в коде/тексте',
      'Search in URL for'                                                                                                                         => 'Искать в URL',
      'Search only'                                                                                                                               => 'Только поиск',
      'Search:'                                                                                                                                   => 'Поиск:',
      'Security token mismatch. The action was not performed. Your session probably expired.'                                                     => 'Несоответствие токена безопасности. Действие не было выполнено. Ваша сессия, вероятно, истекла.',
      'Select protocols the website should work on.'                                                                                              => 'Выберите протокол, на котором должен работать сайт.',
      'Sending with AJAX failed. Sending data the regular way'                                                                                    => 'Отправка через AJAX не удалась. Отправляю данные обычным способом',
      'Sending with AJAX failed. Your server blocks XHR POST requests.'                                                                           => 'Отправка через AJAX не удалась. Ваш сервер блокирует XHR POST запросы.',
      'Serial number has to be in a format of 16 characters XXXXXXXXXXXXXXXX or XXXX-XXXX-XXXX-XXXX'                                              => 'Серийный номер должен быть в формате 16 символов XXXXXXXXXXXXXXXX или XXXX-XXXX-XXXX-XXXX',
      'Serial number'                                                                                                                             => 'Серийный номер',
      'Set a custom directory name instead of .content.xxxxxxxx if you named it differently or you have multiple content directories.'            => 'Укажите название папки вместо .content.xxxxxxxx если вы её переименовали или у вас несколько папок с контентом.',
      'Set only if switch between subdomains is not working correctly.'                                                                           => 'Укажите домен только если переключение между поддоменами не работает правильно.',
      'Set password'                                                                                                                              => 'Поставить пароль',
      'Set rel attribute value for all internal links. E.g. make all external links nofollow.'                                                    => 'Установите значение атрибута rel для всех внешних ссылок. К примеру, поставьте всем внешним ссылкам nofollow.',
      'Set to run the original website on its subdomain or to enable subdomains on another domain.'                                               => 'Укажите, если вы запускаете сайт на поддомене его оригинального домена или для работы поддоменов у другого домена.',
      'Settings were updated.'                                                                                                                    => 'Настройки были обновлены.',
      'Settings'                                                                                                                                  => 'Настройки',
      'Show API key'                                                                                                                              => 'Показать ключ API',
      'Show _MENU_ entries'                                                                                                                       => 'Показать _MENU_ записей',
      'Show settings'                                                                                                                             => 'Показать настройки',
      'Show stats'                                                                                                                                => 'Показать статистику',
      'Show unmodified lines'                                                                                                                     => 'Показать строки без изменений',
      'Showing 0 to 0 of 0 entries'                                                                                                               => 'Записи с 0 до 0 из 0 записей',
      'Showing _START_ to _END_ of _TOTAL_ entries'                                                                                               => 'Записи с _START_ до _END_ из _TOTAL_ записей',
      'Sitemap path'                                                                                                                              => 'Путь к XML-карте сайта',
      'Size'                                                                                                                                      => 'Размер',
      'Some URLencode links containing non-Latin letters or characters might be encoded incorrectly. This tool will fix such links in HTML code.' => 'Некоторые URLencode ссылки, содержащие нелатинские буквы или знаки, могли бы закодированы неправильно. Этот инструмент исправит такие ссылки в HTML коде.',
      'Some websites use version control by adding ?ver=x.x to the end of URLs. This will fix such URLs. It will also fix URLs with PHPSESSID.'   => 'Некоторые сайты используют контроль версий, добавляя ?ver=x.x. в конец URL. Этот инструмент исправит такие URL. Так же он исправит вхождения PHPSESSID.',
      'Start import'                                                                                                                              => 'Запустить импорт',
      'Stats'                                                                                                                                     => 'Статистика',
      'Status'                                                                                                                                    => 'Состояние',
      'Subpattern Modifiers &amp; Assertions'                                                                                                     => 'Модификаторы подгрупп и Утверждения',
      'Switch mode if you need to make an integration with 3rd party system (i.e. Wordpress).'                                                    => 'Переключите режим, если вам необходимо запустить сайт в режиме совместимости со сторонними системами (напр. Wordpress)',
      'System and Website Info'                                                                                                                   => 'Информация о системе и сайте',
      'System check'                                                                                                                              => 'Проверка системы',
      'System update'                                                                                                                             => 'Обновление системы',
      'System'                                                                                                                                    => 'Система',
      'Template %s created.'                                                                                                                      => 'Шаблон %s создан.',
      'Template %s removed.'                                                                                                                      => 'Шаблон %s удалён.',
      'Template %s updated.'                                                                                                                      => 'Шаблон %s обновлён.',
      'Template name can only contain latin letters, numbers, dashes and underscores.'                                                            => 'Название шаблона может состоять только из латинских букв, цифр, тире и нижнего подчёркивания.',
      'Template name'                                                                                                                             => 'Название шаблона',
      'Templates'                                                                                                                                 => 'Шаблоны',
      'The latest Loader version includes files from .content.xxxxxx/includes/ directory.'                                                        => 'Последняя версия Лоадера подгружает файлы для подстановки из подпапки .content.xxxxxx/includes/.',
      'The list has been cleared.'                                                                                                                => 'Список был очищен.',
      'The working directory does not have writing permissions.'                                                                                  => 'У рабочей папки нет прав на запись.',
      'This feature is experimental. You can view all gathered requests from visitors for missing URLs.'                                          => 'Этот функционал является экспериментальным. Вы сможете просматривать все собранные запросы от посетителей на несуществующие URL.',
      'This section is available only when access is restricted by a password Please, set your password first.'                                   => 'Этот раздел доступен только если доступ защищён паролём. Сначала установите пароль.',
      'This tool checks and updates Archivarix CMS, Archivarix Loader to the latest version.'                                                     => 'Проверит и обновит Archivarix CMS и Archivarix Лоадер на последние версии.',
      'This tool correctly converts to UTF-8 all html pages and other types of text files with a non-UTF-8 encoding.'                             => 'Этот инструмент корректно переконвертирует в UTF-8 все html страницы и другие виды текстовых файлов, которые имеют кодировку отличную от UTF-8.',
      'This tool requires following PHP extensions to be installed: %s.'                                                                          => 'Для работы этого инструмента необходимо установить следующие PHP расширения: %s.',
      'This tool will scan all image tags for missing internal urls and remove those image tags.'                                                 => 'Этот инструмент просканирует все теги изображений на отсутствующие локальные и удалит их теги.',
      'This tool will scan all internal links that lead to missing pages and remove that links while keeping anchors.'                            => 'Этот инструмент просканирует все внутренние ссылки, которые ведут на отсутствующие страницы и удалит ссылки сохранив анкоры.',
      'This website only (default)'                                                                                                               => 'Только этот сайт (по-умолчанию)',
      'This website only, 404 for missing URLs'                                                                                                   => 'Только этот сайт, для отсутствующих URL код 404',
      'This will also clear all existing history.'                                                                                                => 'Также удалит всю существующую историю.',
      'This will show 1x1 pixel transparent png for all missing images instead of 404 error.'                                                     => 'Отобразит однопиксельную прозрачную заглушку у всех отсутствующих изображений вместо 404 ошибки.',
      'This will show empty response for all missing css styles instead of 404 error.'                                                            => 'Отдаст пустой .css у всех отсутствующих .css запросов вместо 404 ошибки.',
      'This will show empty response for all missing javascripts instead of 404 error.'                                                           => 'Отдаст пустой .js у всех отсутствующих .js запросов вместо 404 ошибки.',
      'This will show transparent icon for all missing .ico (i.e. favicon.ico) instead of 404 error.'                                             => 'Отдаст прозрачный .ico у всех отсутствующих .ico (напр. favicon.ico) запросов вместо 404 ошибки.',
      'Timeout in seconds'                                                                                                                        => 'Таймаут в секундах',
      'To'                                                                                                                                        => 'До',
      'Tools'                                                                                                                                     => 'Инструменты',
      'Total files'                                                                                                                               => 'Всего файлов',
      'Total hostnames'                                                                                                                           => 'Всего хостов',
      'Total'                                                                                                                                     => 'Всего',
      'Transliterate to latin characters'                                                                                                         => 'Транслитерировать латиницей',
      'Turn OFF Dev Mode'                                                                                                                         => 'Выключить Dev Mode',
      'Turn ON Dev Mode'                                                                                                                          => 'Включить Dev Mode',
      'Tutorials'                                                                                                                                 => 'Инструкции',
      'URI address'                                                                                                                               => 'URI адрес',
      'URL has to contain'                                                                                                                        => 'URL должен содержать',
      'URL'                                                                                                                                       => 'URL',
      'URLs Replaces'                                                                                                                             => 'Замены в URL',
      'URLs menu will have pagination for domains/subdomains with higher number of URLs.'                                                         => 'Будет включена пагинация для меню с URL, если количество URL для домена/поддомена больше.',
      'URLs'                                                                                                                                      => 'URL',
      'Update external links'                                                                                                                     => 'Обновить внешние ссылки',
      'Update'                                                                                                                                    => 'Обновить',
      'Updated %d encoded URLs in %d different pages.'                                                                                            => 'Обновлено %d неправильно закодированных URL в %d страницах.',
      'Updated %d external links in %d different pages'                                                                                           => 'Обновлено %d внешних ссылок в %d страницах',
      'Upload'                                                                                                                                    => 'Загрузить',
      'Uploaded .zip file has incorrect structure'                                                                                                => 'Загруженный .zip файл имеет неправильную струкутуру',
      'Uploads'                                                                                                                                   => 'Загрузки',
      'Version'                                                                                                                                   => 'Версия',
      'Versions in CSS and JS'                                                                                                                    => 'Версии в CSS и JS',
      'Viewport meta tag'                                                                                                                         => 'Метатег viewport',
      'Viewport set in %d different pages.'                                                                                                       => 'Viewport добавлен в %d страницах.',
      'WYSIWYG'                                                                                                                                   => 'Визуальный редактор',
      'Warning! IP restriction or password is not configured. Anybody can access this page.'                                                      => 'Внимание! Ограничение по IP или паролю не настроено. Кто угодно может видеть эту страницу.',
      'We have to use a legacy .db file because you have outdated SQLite version. Minimum recommended version is 3.7.0'                           => 'Мы вынуждены использовать старый тип фала .db, потому что у вас устаревщая версия SQLite. Минимальная рекомендуемая версия 3.7.0',
      'We recommend creating clones in the same directory as originals.'                                                                          => 'Рекомендуем создавать копии в той же директории, что и оригинал.',
      'Website conversion to UTF-8'                                                                                                               => 'Конвертация сайта в UTF-8',
      'Website crawling has been allowed in robots.txt.'                                                                                          => 'Индексация сайта в robots.txt разрешена.',
      'Website is missing or not installed yet.'                                                                                                  => 'Рабочий сайт отсутствует или ещё не был установлен.',
      'Website'                                                                                                                                   => 'Сайт',
      'Websites import'                                                                                                                           => 'Импорт сайтов',
      'Work with external links'                                                                                                                  => 'Работа с внешними ссылками',
      'YYYYMMDDHHMMSS'                                                                                                                            => 'ГГГГММДДЧЧММСС',
      'Yes'                                                                                                                                       => 'Да',
      'You already have the latest version %s of %s.'                                                                                             => 'У вас уже установлена последняя версия %s %s',
      'You can also remove all external links but keep the anchor text and content.'                                                              => 'Вы также можете удалить все внешние ссылки, сохранив анкоры и содержимое.',
      'You cannot create a URL with a path that already exists.'                                                                                  => 'Нельзя создать URL с уже существующим путём.',
      'You cannot create or edit custom files with a php code under a safe mode.'                                                                 => 'Нельзя создавать или редактировать кастомные файлы с php кодом в безопасном режиме.',
      'a or b or c'                                                                                                                               => 'a или b или c',
      'a or b'                                                                                                                                    => 'a или b',
      'action'                                                                                                                                    => 'действие',
      'ago'                                                                                                                                       => 'назад',
      'any character'                                                                                                                             => 'любой символ',
      'any non "word" characters'                                                                                                                 => 'не символ "слова"',
      'any non digit character'                                                                                                                   => 'нецифровой символ',
      'any non whitespace character'                                                                                                              => 'непробельный символ',
      'approx.'                                                                                                                                   => 'прибл.',
      'at least n occurrences'                                                                                                                    => 'b и более повторений',
      'at most m occurrences'                                                                                                                     => 'менее m повторений',
      'between n and m occurrences (inclusive)'                                                                                                   => 'между n и m повторений (включая)',
      'comment'                                                                                                                                   => 'комментарий',
      'conditional subpattern (if then else)'                                                                                                     => 'условие (если, то а иначе)',
      'conditional subpattern (if then)'                                                                                                          => 'условие (если, то)',
      'digits (0-9)'                                                                                                                              => 'цифровой символ (0-9)',
      'digits between 0 and 7 (inclusive)'                                                                                                        => 'цифры между 0 и 7',
      'end character class defintion'                                                                                                             => 'конец символьного класса',
      'end of subject'                                                                                                                            => 'конец объекта',
      'end of text'                                                                                                                               => 'конец текста',
      'end subpattern'                                                                                                                            => 'конец группы',
      'letters between a and h (inclusive)'                                                                                                       => 'буквы между a и h',
      'n occurrences exactly'                                                                                                                     => 'ровно n повторений',
      'negative look ahead assertion'                                                                                                             => 'негативный просмотр вперёд',
      'negative look behind assertion'                                                                                                            => 'негативный просмотр назад',
      'non capturing subpattern'                                                                                                                  => 'пассивная группа',
      'not a and not b and not c'                                                                                                                 => 'не a, не b и не c',
      'not a word boundary'                                                                                                                       => 'не граница слова',
      'off'                                                                                                                                       => 'выключено',
      'once-only subpattern'                                                                                                                      => 'однократное подвыражение',
      'one or more'                                                                                                                               => 'один или более',
      'or select'                                                                                                                                 => 'или выбрать',
      'or'                                                                                                                                        => 'или',
      'positive look ahead assertion'                                                                                                             => 'позитивный просмотр вперёд',
      'positive look behind assertion'                                                                                                            => 'позитивный просмотр назад',
      'show files'                                                                                                                                => 'показать файлы',
      'show'                                                                                                                                      => 'показать',
      'size in bytes'                                                                                                                             => 'размер в байтах',
      'start character class definition'                                                                                                          => 'начало символьного класса',
      'start of subject'                                                                                                                          => 'начало объекта',
      'start of text'                                                                                                                             => 'начало текста',
      'start subpattern'                                                                                                                          => 'начало группы',
      'subdomain'                                                                                                                                 => 'поддомен',
      'ungreedy between n and m occurrences (inclusive)'                                                                                          => 'нежадные между n и m повторений (включая)',
      'ungreedy one or more'                                                                                                                      => 'ленивые один или более',
      'ungreedy zero or more'                                                                                                                     => 'ленивые ноль или более',
      'ungreedy zero or one occurrence'                                                                                                           => 'ленивые ноль или одно повторение',
      'visits'                                                                                                                                    => 'посещений',
      'whitespace (space, tab, CRLF)'                                                                                                             => 'пробельный символ',
      'word boundary'                                                                                                                             => 'граница слова',
      'zero or more'                                                                                                                              => 'ноль или более',
      'zero or one occurrence'                                                                                                                    => 'ноль или одно повторение',
    ),
  );

  if ( isset( $localization[$languageCode] ) ) {
    return $localization[$languageCode];
  }
}

function matchCidr( $ip, $cidr )
{
  if ( strpos( $cidr, '/' ) == false ) {
    $cidr .= '/32';
  }
  list( $cidr, $netmask ) = explode( '/', $cidr, 2 );
  $range_decimal    = ip2long( $cidr );
  $ip_decimal       = ip2long( $ip );
  $wildcard_decimal = pow( 2, ( 32 - $netmask ) ) - 1;
  $netmask_decimal  = ~$wildcard_decimal;
  return ( ( $ip_decimal & $netmask_decimal ) == ( $range_decimal & $netmask_decimal ) );
}

function newPDO()
{
  global $dsn;
  return new PDO( $dsn );
}

function packLoaderSettings()
{
  global $sourcePath;
  $customFilesSettings = [];
  $customFiles         = getCustomFiles();
  if ( !empty( $customFiles ) ) foreach ( $customFiles as $customFile ) {
    if ( !$customFile['is_dir'] ) {
      $customFilesSettings['ARCHIVARIX_CUSTOM_FILES'][] = [
        'filename' => $customFile['filename'],
        'content'  => base64_encode( file_get_contents( $sourcePath . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . $customFile['filename'] ) ),
      ];
    }
  }
  return json_encode( array_merge( loadLoaderSettings(), $customFilesSettings ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
}

function pathExists( $hostname, $path )
{
  $pdo  = newPDO();
  $stmt = $pdo->prepare( "SELECT rowid FROM structure WHERE hostname = :hostname AND request_uri = :path LIMIT 1" );
  $stmt->execute( [
    'hostname' => $hostname,
    'path'     => encodePath( $path ),
  ] );
  $stmt->execute();
  $id = $stmt->fetchColumn();
  if ( $id ) {
    return $id;
  }
}

function printArrayHuman( $array, $return = false )
{
  array_walk_recursive( $array, "escapeArrayValues" );
  if ( $return ) {
    return $array;
  } else {
    print_r( $array );
  }
}

function printArrayList( $tree, $pathUrls, $dir = '' )
{

  echo '<ul class="d-none">';

  foreach ( $tree as $k => $v ) {
    if ( is_array( $v ) ) {
      echo '<li data-jstree=\'{"icon":"far fa-folder","disabled":true,"order":1}\'>/' . htmlspecialchars( rawurldecode( substr( $k, 1 ) ), ENT_IGNORE );
      printArrayList( $v, $pathUrls, $dir . '/' . substr( $k, 1 ) );
      echo '</li>';
      continue;
    } else {
      if ( $v == '' || $v == '/' ) {
        $path = $dir . $v;
      } else {
        $path = $dir . '/' . $v;
      }

      if ( isset( $pathUrls[$path] ) ) {
        echo getTreeLi( $pathUrls[$path] ) . '</li>';
      } else {
        echo '<li data-jstree=\'{"icon":"far fa-folder","disabled":true,"order":1}\'>/' . htmlspecialchars( rawurldecode( $v ), ENT_IGNORE ) . '/</li>';
      }
    }
  }

  echo '</ul>';
}

function printFormFields( $array )
{
  if ( empty( $array ) ) return;
  $flattenArray = flattenArray( $array );
  foreach ( $flattenArray as $key => $val ) {
    $keys = explode( ',', $key );
    if ( count( $keys ) > 1 ) {
      $name = $keys[0] . '[';
      unset( $keys[0] );
      $name .= implode( '][', $keys ) . ']';

    } else {
      $name = $keys[0];
    }
    echo '<input type="hidden" name="' . $name . '" value="' . htmlspecialchars( $val ) . '">';
  }
}

function printStats( $stats )
{
// [TODO] graphs could be called separately
// graph mimetype and count
// graph mimetype and size
// graph hostnames and count
// graph local redirects

  if ( empty( $stats['mimestats'] ) ) return;
  ?>
  <div class="row justify-content-center">

    <?php
    // mime chart count
    unset( $mime );
    $mime[]         = ['mimetype', 'count'];
    $mimeTotalCount = $stats['filescount'];
    foreach ( $stats['mimestats'] as $mimeItem ) {
      $mime[] = [$mimeItem['mimetype'], (int)$mimeItem['count']];
    }
    ?>
    <div class="col-12 col-lg-6">
      <div class="card border-0 shadow mb-3">
        <div class="card-header bg-dark text-light">
          <i class="fas fa-chart-pie fa-fw"></i> <?=L( 'MIME-types and quantity' )?>
        </div>
        <div class="card-body p-1 justify-content-center">
          <div id="div_mimestats_count_<?=$stats['id']?>" class="p-0  m-0" style="min-height:380px;"></div>
          <script type="text/javascript">
            google.charts.load("current", {
              packages: ["corechart"],
              language: '<?=$_SESSION['archivarix.lang']?>'
            });
            google.charts.setOnLoadCallback(drawStatsMimeCount_<?=$stats['id']?>);

            function drawStatsMimeCount_<?=$stats['id']?>() {
              var mimew = document.getElementById('div_mimestats_count_<?=$stats['id']?>').offsetWidth;
              var data = google.visualization.arrayToDataTable(<?=json_encode( $mime )?>);
              var options = {
                pieHole: 0.4,
                chartArea: {
                  left: 10,
                  right: 10,
                  top: 10,
                  bottom: 10,
                  width: '100%',
                  height: '350'
                },
                legend: {position: 'labeled'},
                pieSliceText: 'value'
              };
              if (mimew < 500) {
                options.legend.position = 'none';
              }
              var chart_<?=$stats['id']?> = new google.visualization.PieChart(document.getElementById('div_mimestats_count_<?=$stats['id']?>'));
              chart_<?=$stats['id']?>.draw(data, options);
            }
          </script>

        </div>
        <div class="card-footer bg-white">
          <?=L( 'Total files' )?>: <?=number_format( $mimeTotalCount )?>
        </div>
      </div>
    </div>

    <?php
    // mime chart size
    unset( $mime );
    $mime[]        = ['mimetype', 'size'];
    $mimeTotalSize = $stats['filessize'];
    foreach ( $stats['mimestats'] as $mimeItem ) {
      $mime[] = [$mimeItem['mimetype'], ['v' => (int)$mimeItem['size'], 'f' => getHumanSize( $mimeItem['size'] )]];
    }
    ?>
    <div class="col-12 col-lg-6">
      <div class="card border-0 shadow mb-3">
        <div class="card-header bg-dark text-light">
          <i class="fas fa-chart-pie fa-fw"></i> <?=L( 'MIME-types and sizes' )?>
        </div>
        <div class="card-body p-1">
          <div id="div_mimestats_size_<?=$stats['id']?>" class="p-0 m-0" style="min-height:380px;"></div>
          <script type="text/javascript">
            google.charts.load("current", {
              packages: ["corechart"],
              language: '<?=$_SESSION['archivarix.lang']?>'
            });
            google.charts.setOnLoadCallback(drawStatsMimeSize_<?=$stats['id']?>);

            function drawStatsMimeSize_<?=$stats['id']?>() {
              var mimew = document.getElementById('div_mimestats_size_<?=$stats['id']?>').offsetWidth;
              var data = google.visualization.arrayToDataTable(<?=json_encode( $mime )?>);
              var options = {
                pieHole: 0.4,
                chartArea: {
                  left: 10,
                  right: 10,
                  top: 10,
                  bottom: 10,
                  width: '100%',
                  height: '350'
                },
                legend: {position: 'labeled'},
                pieSliceText: 'value'
              };
              if (mimew < 500) {
                options.legend.position = 'none';
              }
              var chart = new google.visualization.PieChart(document.getElementById('div_mimestats_size_<?=$stats['id']?>'));
              chart.draw(data, options);
            }
          </script>
        </div>
        <div class="card-footer bg-white">
          <?=L( 'Total' )?>: <?=L( 'approx.' )?> <?=getHumanSize( $mimeTotalSize )?>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card border-0 shadow mb-3">
        <div class="card-header bg-dark text-light">
          <i class="fas fa-chart-pie fa-fw"></i> <?=L( 'Hostnames and URLs count / size' )?>
        </div>
        <div class="card-body p-0 justify-content-center small">
          <table class="table table-responsive table-hover m-0">
            <thead>
            <tr class="table-secondary">
              <th scope="col" class="w-100"><?=L( 'Hostnames' )?></th>
              <th scope="col" class="text-center"><?=L( 'Files' )?></th>
              <th scope="col" class="text-center"><?=L( 'Size' )?></th>
              <th scope="col" class="text-center"><?=L( 'Redirects' )?></th>
            </tr>
            </thead>
            <tbody>
            <?php
            $hostnamesTotalCount = 0;
            foreach ( $stats['hostnames'] as $hostname ) {
              $hostnamesTotalCount++; ?>
              <tr>
                <th scope="row"><?=convertIdnToUtf8( $hostname['hostname'] )?></th>
                <td class="text-center"><?=number_format( $hostname['count'], 0 )?></td>
                <td class="text-center"><?=getHumanSize( $hostname['size'] )?></td>
                <td class="text-center"><?=number_format( $hostname['redirects'], 0 )?></td>
              </tr>
            <?php } ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>

  <?php
}

function purgeCacheCloudflare( $urls = false )
{
  $acmsCloudflare = readMetaData( 'acms_cloudflare' );
  $path           = '/client/v4/zones/' . $acmsCloudflare['data']['zone_id'] . '/purge_cache';
  $data           = '{"purge_everything":true}';
  $response       = sendRequestCloudflare( 'POST', $path, $data );
  if ( !empty( $response['success'] ) ) {
    return true;
  }
}

function putLoader( $path )
{
  $loaderFile = tempnam( getTempDirectory(), 'archivarix.' );
  if ( !empty( $_POST['settings']['integration'] ) ) {
    downloadFile( 'https://archivarix.com/download/archivarix.loader.integration.zip', $loaderFile );
    $zip = new ZipArchive();
    $zip->open( $loaderFile );
    if ( file_exists( __DIR__ . DIRECTORY_SEPARATOR . '.htaccess' ) ) {
      $pathHtaccess        = __DIR__ . DIRECTORY_SEPARATOR . '.htaccess';
      $currentHtaccessData = file_get_contents( $pathHtaccess );
      file_put_contents( $pathHtaccess, $zip->getFromName( '.htaccess' ) . "\n\n# BACKUP BELOW\n" . $currentHtaccessData );
    } else {
      $zip->extractTo( $path, '.htaccess' );
    }
    $zip->extractTo( $path, 'archivarix.php' );
    $zip->close();
  } else {
    downloadFile( 'https://archivarix.com/download/archivarix.loader.install.zip', $loaderFile );
    $zip = new ZipArchive();
    $zip->open( $loaderFile );
    $zip->extractTo( $path );
    $zip->close();
  }
}

function recoverBackup( $params, $taskOffset = 0 )
{
  global $taskIncomplete;
  global $taskIncompleteOffset;
  global $taskStats;
  global $ACMS;
  $pdo = newPDO();

  if ( !empty( unserialize( $taskStats ) ) ) {
    $stats = unserialize( $taskStats );
  } else {
    $stats = ['pages' => 0, 'processed' => 0, 'total' => 0];
  }

  if ( $taskOffset == 0 ) $taskOffset = 100000000;

  if ( isset( $params['all'] ) ) {
    if ( empty( $stats['total'] ) ) $stats['total'] = $pdo->query( "SELECT COUNT(1) FROM backup" )->fetchColumn();
    $stmt = $pdo->prepare( "SELECT rowid, * FROM backup WHERE rowid <= :taskOffset ORDER BY rowid DESC" );
    $stmt->execute( ['taskOffset' => $taskOffset] );
    while ( $backup = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
      recoverFile( $backup );
      $stats['pages']++;
      $stats['processed']++;

      if ( $ACMS['ACMS_TIMEOUT'] && ( microtime( true ) - ACMS_START_TIME ) > ( $ACMS['ACMS_TIMEOUT'] - 1 ) ) {
        $taskStats            = serialize( $stats );
        $taskIncomplete       = true;
        $taskIncompleteOffset = $backup['rowid'];
        return $stats;
      }
    }
    $stmt_remove_all = $pdo->prepare( "DELETE FROM backup" );
    $stmt_remove_all->execute();
    return;
  }

  if ( !empty( $params['breakpoint'] ) ) {
    $pdo2 = newPDO();
    if ( empty( $stats['total'] ) ) {
      $stmt = $pdo->prepare( "SELECT COUNT(1) FROM backup WHERE rowid > :breakpoint" );
      $stmt->execute( ['breakpoint' => $params['breakpoint']] );
      $stats['total'] = $stmt->fetchColumn();
    }
    $stmt = $pdo->prepare( "SELECT rowid, * FROM backup WHERE rowid > :breakpoint AND rowid <= :taskOffset ORDER BY rowid DESC" );
    $stmt->execute( ['breakpoint' => $params['breakpoint'], 'taskOffset' => $taskOffset] );
    while ( $backup = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
      recoverFile( $backup );
      $stmt_delete_backup = $pdo2->prepare( "DELETE FROM backup WHERE rowid = :backupid" );
      $stmt_delete_backup->execute( ['backupid' => $backup['rowid']] );
      $stats['pages']++;
      $stats['processed']++;

      if ( $ACMS['ACMS_TIMEOUT'] && ( microtime( true ) - ACMS_START_TIME ) > ( $ACMS['ACMS_TIMEOUT'] - 1 ) ) {
        $taskStats            = serialize( $stats );
        $taskIncomplete       = true;
        $taskIncompleteOffset = $backup['rowid'];
        return $stats;
      }
    }
    return;
  }

  $backups = explode( ',', $params['backups'] );
  rsort( $backups );
  foreach ( $backups as $backupId ) {
    $stmt = $pdo->prepare( "SELECT rowid, * FROM backup WHERE rowid = :rowid" );
    $stmt->execute( ['rowid' => $backupId] );
    while ( $backup = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
      recoverFile( $backup );
    }
    $stmt = $pdo->prepare( "DELETE FROM backup WHERE rowid = :rowid" );
    $stmt->execute( ['rowid' => $backupId] );
  }

  responseAjax();
}

function readMetaData( $name )
{
  createTable( 'meta' );
  $pdo  = newPDO();
  $stmt = $pdo->prepare( "SELECT * FROM meta WHERE name = :name" );
  $stmt->bindParam( 'name', $name, PDO::PARAM_STR );
  $stmt->execute();

  $result = $stmt->fetch( PDO::FETCH_ASSOC );
  if ( isset( $result['data'] ) ) {
    $result['data'] = json_decode( $result['data'], true );
  }
  return $result;
}

function recoverFile( $backup )
{
  $metaData = json_decode( $backup['settings'], true );

  global $sourcePath;
  $pdo = newPDO();

  switch ( $backup['action'] ) :
    case 'breakpoint' :
      break;
    case 'remove' :
      $stmt_backup = $pdo->prepare( "INSERT INTO structure (rowid, url, protocol, hostname, request_uri, folder, filename, mimetype, charset, filesize, filetime, url_original, enabled, redirect) VALUES (:rowid, :url, :protocol, :hostname, :request_uri, :folder, :filename, :mimetype, :charset, :filesize, :filetime, :url_original, :enabled, :redirect)" );
      $stmt_backup->execute( [
        'url'          => $metaData['url'],
        'protocol'     => $metaData['protocol'],
        'hostname'     => $metaData['hostname'],
        'request_uri'  => $metaData['request_uri'],
        'folder'       => $metaData['folder'],
        'filename'     => $metaData['filename'],
        'mimetype'     => $metaData['mimetype'],
        'charset'      => $metaData['charset'],
        'filesize'     => $metaData['filesize'],
        'filetime'     => $metaData['filetime'],
        'url_original' => $metaData['url_original'],
        'enabled'      => $metaData['enabled'],
        'redirect'     => $metaData['redirect'],
        'rowid'        => $metaData['rowid'],
      ] );
      rename( $sourcePath . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . $backup['filename'], $sourcePath . DIRECTORY_SEPARATOR . $metaData['folder'] . DIRECTORY_SEPARATOR . $metaData['filename'] );
      break;
    case 'create' :
      $metaDataCurrent = getMetaData( $metaData['rowid'] );
      unlink( $sourcePath . DIRECTORY_SEPARATOR . $metaDataCurrent['folder'] . DIRECTORY_SEPARATOR . $metaDataCurrent['filename'] );
      $stmt_backup = $pdo->prepare( "DELETE FROM structure WHERE rowid = :rowid" );
      $stmt_backup->execute( [
        'rowid' => $metaData['rowid'],
      ] );
      break;
    default :
      $metaDataCurrent = getMetaData( $metaData['rowid'] );
      unlink( $sourcePath . DIRECTORY_SEPARATOR . $metaDataCurrent['folder'] . DIRECTORY_SEPARATOR . $metaDataCurrent['filename'] );
      $stmt_backup = $pdo->prepare( "UPDATE structure SET url = :url, protocol = :protocol, hostname = :hostname, request_uri = :request_uri, folder = :folder, filename = :filename, mimetype = :mimetype, charset = :charset, filesize = :filesize, filetime = :filetime, url_original = :url_original, enabled = :enabled, redirect = :redirect WHERE rowid = :rowid" );
      $stmt_backup->execute( [
        'url'          => $metaData['url'],
        'protocol'     => $metaData['protocol'],
        'hostname'     => $metaData['hostname'],
        'request_uri'  => $metaData['request_uri'],
        'folder'       => $metaData['folder'],
        'filename'     => $metaData['filename'],
        'mimetype'     => $metaData['mimetype'],
        'charset'      => $metaData['charset'],
        'filesize'     => $metaData['filesize'],
        'filetime'     => $metaData['filetime'],
        'url_original' => $metaData['url_original'],
        'enabled'      => $metaData['enabled'],
        'redirect'     => $metaData['redirect'],
        'rowid'        => $metaData['rowid'],
      ] );
      rename( $sourcePath . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . $backup['filename'], $sourcePath . DIRECTORY_SEPARATOR . $metaData['folder'] . DIRECTORY_SEPARATOR . $metaData['filename'] );
  endswitch;
}

function removeApiKey()
{
  $pdo = newPDO();
  if ( $pdo->exec( "DELETE FROM settings WHERE param = 'apikey'" ) ) return true;
}

function removeBrokenImages( $taskOffset = 0 )
{
  global $taskIncomplete;
  global $taskIncompleteOffset;
  global $taskStats;
  global $sourcePath;
  global $uuidSettings;
  global $ACMS;

  if ( !empty( unserialize( $taskStats ) ) ) {
    $stats = unserialize( $taskStats );
  } else {
    $stats = ['images' => 0, 'pages' => 0, 'processed' => 0, 'total' => 0];
  }
  if ( function_exists( 'libxml_use_internal_errors' ) ) libxml_use_internal_errors( true );
  $pdo = newPDO();
  if ( empty( $stats['total'] ) ) $stats['total'] = $pdo->query( "SELECT COUNT(1) FROM structure WHERE mimetype = 'text/html'" )->fetchColumn();
  $stmt = $pdo->prepare( "SELECT rowid, * FROM structure WHERE mimetype = 'text/html' AND rowid > :taskOffset ORDER BY rowid" );
  $stmt->execute( ['taskOffset' => $taskOffset] );
  while ( $url = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
    $updatedImages = 0;
    $file          = $sourcePath . DIRECTORY_SEPARATOR . $url['folder'] . DIRECTORY_SEPARATOR . $url['filename'];
    if ( !is_file( $file ) ) continue;
    $html = file_get_contents( $file );
    if ( !strlen( $html ) ) continue;
    $html = convertEncoding( convertHtmlEncoding( $html, 'utf-8', $url['charset'] ), 'html-entities', 'utf-8' );
    unset( $dom );
    $dom                      = new DOMDocument();
    $dom->formatOutput        = true;
    $dom->documentURI         = $url['url'];
    $dom->strictErrorChecking = false;
    $dom->encoding            = 'utf-8';
    $dom->loadHTML( $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
    $imgTags = $dom->getElementsByTagName( 'img' );
    for ( $n = $imgTags->length - 1; $n >= 0; --$n ) {
      $hrefAttribute = $imgTags->item( $n )->getAttribute( 'src' );
      $hrefAbsolute  = rawurldecode( getAbsolutePath( $url['url'], $hrefAttribute ) );
      if ( !empty( $_POST['check_files'] ) ) {
        $hrefFilePath = parse_url( $hrefAbsolute, PHP_URL_PATH );
        if ( file_exists( $sourcePath . DIRECTORY_SEPARATOR . '..' . $hrefFilePath ) ) continue;
      }
      $hrefHostname = strtolower( convertIdnToAscii( parse_url( $hrefAbsolute, PHP_URL_HOST ) ) );
      $hrefAbsolute = encodeUrl( $hrefAbsolute );
      $hrefVariants = [$hrefAbsolute];
      if ( preg_match( '~[/]+$~', $hrefAbsolute ) ) {
        $hrefVariants[] = preg_replace( '~[/]+$~', '', $hrefAbsolute );
      } elseif ( !parse_url( $hrefAbsolute, PHP_URL_QUERY ) && !parse_url( $hrefAbsolute, PHP_URL_FRAGMENT ) ) {
        $hrefVariants[] = $hrefAbsolute . '/';
      }
      if ( !preg_match( '~^([-a-z0-9.]+\.)?' . preg_quote( $uuidSettings['domain'], '~' ) . '$~i', $hrefHostname ) ) continue;
      if ( !urlExists( $hrefVariants ) ) {
        $updatedImages++;
        $imgTags->item( $n )->parentNode->removeChild( $imgTags->item( $n ) );
      }
    }
    if ( $updatedImages ) {
      backupFile( $url['rowid'], 'edit' );
      file_put_contents( $file, convertHtmlEncoding( html_entity_decode( $dom->saveHTML() ), $url['charset'], 'utf-8' ) );
      updateFilesize( $url['rowid'], filesize( $file ) );
      $stats['images'] += $updatedImages;
    }
    $stats['pages']++;
    $stats['processed']++;

    if ( $ACMS['ACMS_TIMEOUT'] && ( microtime( true ) - ACMS_START_TIME ) > ( $ACMS['ACMS_TIMEOUT'] - 1 ) ) {
      $taskStats            = serialize( $stats );
      $taskIncomplete       = true;
      $taskIncompleteOffset = $url['rowid'];
      return $stats;
    }
  }
  if ( $stats['images'] ) createBackupBreakpoint( L( 'Remove broken images' ) . '. ' . sprintf( L( 'Processed: %s' ), number_format( $stats['images'], 0 ) ) );
  return $stats;
}

function removeBrokenLinks( $taskOffset = 0 )
{
  global $taskIncomplete;
  global $taskIncompleteOffset;
  global $taskStats;
  global $sourcePath;
  global $uuidSettings;
  global $ACMS;

  if ( !empty( unserialize( $taskStats ) ) ) {
    $stats = unserialize( $taskStats );
  } else {
    $stats = ['links' => 0, 'pages' => 0, 'processed' => 0, 'total' => 0];
  }
  if ( function_exists( 'libxml_use_internal_errors' ) ) libxml_use_internal_errors( true );
  $pdo = newPDO();
  if ( empty( $stats['total'] ) ) $stats['total'] = $pdo->query( "SELECT COUNT(1) FROM structure WHERE mimetype = 'text/html'" )->fetchColumn();
  $stmt = $pdo->prepare( "SELECT rowid, * FROM structure WHERE mimetype = 'text/html' AND rowid > :taskOffset ORDER BY rowid" );
  $stmt->execute( ['taskOffset' => $taskOffset] );
  while ( $url = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
    $updatedLinks = 0;
    $file         = $sourcePath . DIRECTORY_SEPARATOR . $url['folder'] . DIRECTORY_SEPARATOR . $url['filename'];
    if ( !is_file( $file ) ) continue;
    $html = file_get_contents( $file );
    if ( !strlen( $html ) ) continue;
    $html = convertEncoding( convertHtmlEncoding( $html, 'utf-8', $url['charset'] ), 'html-entities', 'utf-8' );
    unset( $dom );
    $dom                      = new DOMDocument();
    $dom->formatOutput        = true;
    $dom->documentURI         = $url['url'];
    $dom->strictErrorChecking = false;
    $dom->encoding            = 'utf-8';
    $dom->loadHTML( $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
    $linkTags = $dom->getElementsByTagName( 'a' );
    for ( $n = $linkTags->length - 1; $n >= 0; --$n ) {
      $hrefAttribute = $linkTags->item( $n )->getAttribute( 'href' );
      $hrefAbsolute  = rawurldecode( getAbsolutePath( $url['url'], $hrefAttribute ) );
      if ( !empty( $_POST['check_files'] ) ) {
        $hrefFilePath = parse_url( $hrefAbsolute, PHP_URL_PATH );
        if ( file_exists( $sourcePath . DIRECTORY_SEPARATOR . '..' . $hrefFilePath ) ) continue;
      }
      $hrefHostname = strtolower( convertIdnToAscii( parse_url( $hrefAbsolute, PHP_URL_HOST ) ) );
      $hrefAbsolute = encodeUrl( $hrefAbsolute );
      $hrefVariants = [$hrefAbsolute];
      if ( preg_match( '~[/]+$~', $hrefAbsolute ) ) {
        $hrefVariants[] = preg_replace( '~[/]+$~', '', $hrefAbsolute );
      } elseif ( !parse_url( $hrefAbsolute, PHP_URL_QUERY ) && !parse_url( $hrefAbsolute, PHP_URL_FRAGMENT ) ) {
        $hrefVariants[] = $hrefAbsolute . '/';
      }
      if ( !preg_match( '~^([-a-z0-9.]+\.)?' . preg_quote( $uuidSettings['domain'], '~' ) . '$~i', $hrefHostname ) ) continue;
      if ( !urlExists( $hrefVariants ) ) {
        $updatedLinks++;
        while ( $linkTags->item( $n )->hasChildNodes() ) {
          $linkTagChild = $linkTags->item( $n )->removeChild( $linkTags->item( $n )->firstChild );
          $linkTags->item( $n )->parentNode->insertBefore( $linkTagChild, $linkTags->item( $n ) );
        }
        $linkTags->item( $n )->parentNode->removeChild( $linkTags->item( $n ) );
      }
    }
    if ( $updatedLinks ) {
      backupFile( $url['rowid'], 'edit' );
      file_put_contents( $file, convertHtmlEncoding( html_entity_decode( $dom->saveHTML() ), $url['charset'], 'utf-8' ) );
      updateFilesize( $url['rowid'], filesize( $file ) );
      $stats['links'] += $updatedLinks;
    }
    $stats['pages']++;
    $stats['processed']++;

    if ( $ACMS['ACMS_TIMEOUT'] && ( microtime( true ) - ACMS_START_TIME ) > ( $ACMS['ACMS_TIMEOUT'] - 1 ) ) {
      $taskStats            = serialize( $stats );
      $taskIncomplete       = true;
      $taskIncompleteOffset = $url['rowid'];
      return $stats;
    }
  }
  if ( $stats['links'] ) createBackupBreakpoint( L( 'Remove broken links' ) . '. ' . sprintf( L( 'Processed: %s' ), number_format( $stats['links'], 0 ) ) );
  return $stats;
}

function removeMetaTableData( $name )
{
  $pdo  = newPDO();
  $stmt = $pdo->prepare( "DELETE FROM meta WHERE name = :name" );
  $stmt->bindParam( 'name', $name, PDO::PARAM_STR );
  $stmt->execute();
}

function removeExternalLinks( $taskOffset = 0 )
{
  global $taskIncomplete;
  global $taskIncompleteOffset;
  global $taskStats;
  global $sourcePath;
  global $uuidSettings;
  global $ACMS;

  if ( !empty( unserialize( $taskStats ) ) ) {
    $stats = unserialize( $taskStats );
  } else {
    $stats = ['links' => 0, 'pages' => 0, 'processed' => 0, 'total' => 0];
  }
  if ( function_exists( 'libxml_use_internal_errors' ) ) libxml_use_internal_errors( true );
  $pdo = newPDO();
  if ( empty( $stats['total'] ) ) $stats['total'] = $pdo->query( "SELECT COUNT(1) FROM structure WHERE mimetype = 'text/html'" )->fetchColumn();
  $stmt = $pdo->prepare( "SELECT rowid, * FROM structure WHERE mimetype = 'text/html' AND rowid > :taskOffset ORDER BY rowid" );
  $stmt->execute( ['taskOffset' => $taskOffset] );
  while ( $url = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
    $updatedLinks = 0;
    $file         = $sourcePath . DIRECTORY_SEPARATOR . $url['folder'] . DIRECTORY_SEPARATOR . $url['filename'];
    if ( !is_file( $file ) ) continue;
    $html = file_get_contents( $file );
    if ( !strlen( $html ) ) continue;
    $html = convertEncoding( convertHtmlEncoding( $html, 'utf-8', $url['charset'] ), 'html-entities', 'utf-8' );
    unset( $dom );
    $dom                      = new DOMDocument();
    $dom->formatOutput        = true;
    $dom->documentURI         = $url['url'];
    $dom->strictErrorChecking = false;
    $dom->encoding            = 'utf-8';
    $dom->loadHTML( $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
    $linkTags = $dom->getElementsByTagName( 'a' );
    for ( $n = $linkTags->length - 1; $n >= 0; --$n ) {
      $hrefAttribute = $linkTags->item( $n )->getAttribute( 'href' );
      $hrefAbsolute  = rawurldecode( getAbsolutePath( $url['url'], $hrefAttribute ) );
      $hrefHostname  = strtolower( convertIdnToAscii( parse_url( $hrefAbsolute, PHP_URL_HOST ) ) );
      if ( preg_match( '~^([-a-z0-9.]+\.)?' . preg_quote( $uuidSettings['domain'], '~' ) . '$~i', $hrefHostname ) ) continue;
      $updatedLinks++;
      while ( $linkTags->item( $n )->hasChildNodes() ) {
        $linkTagChild = $linkTags->item( $n )->removeChild( $linkTags->item( $n )->firstChild );
        $linkTags->item( $n )->parentNode->insertBefore( $linkTagChild, $linkTags->item( $n ) );
      }
      $linkTags->item( $n )->parentNode->removeChild( $linkTags->item( $n ) );
    }
    if ( $updatedLinks ) {
      backupFile( $url['rowid'], 'edit' );
      file_put_contents( $file, convertHtmlEncoding( html_entity_decode( $dom->saveHTML() ), $url['charset'], 'utf-8' ) );
      updateFilesize( $url['rowid'], filesize( $file ) );
      $stats['links'] += $updatedLinks;
    }
    $stats['pages']++;
    $stats['processed']++;

    if ( $ACMS['ACMS_TIMEOUT'] && ( microtime( true ) - ACMS_START_TIME ) > ( $ACMS['ACMS_TIMEOUT'] - 1 ) ) {
      $taskStats            = serialize( $stats );
      $taskIncomplete       = true;
      $taskIncompleteOffset = $url['rowid'];
      return $stats;
    }
  }
  if ( $stats['links'] ) createBackupBreakpoint( L( 'Remove external links' ) . '. ' . sprintf( L( 'Processed: %s' ), number_format( $stats['links'], 0 ) ) );
  return $stats;
}

function removeImport( $filename )
{
  global $sourcePath;
  $filename = basename( $filename );
  $filename = $sourcePath . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . $filename;
  if ( !file_exists( $filename ) ) {
    return;
  }
  if ( unlink( $filename ) ) {
    return true;
  }
}

function removeTemplate( $name )
{
  global $sourcePath;
  $metaData = getTemplate( $name );
  if ( empty( $metaData ) ) return false;
  $pdo  = newPDO();
  $stmt = $pdo->prepare( "DELETE FROM templates WHERE name = :name" );
  $stmt->bindParam( 'name', $metaData['name'], PDO::PARAM_STR );
  $stmt->execute();
  unlink( $sourcePath . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . $metaData['name'] . '.html' );
  return true;
}

function removeUrl( $id )
{
  global $sourcePath;

  backupFile( $id, 'remove' );
  $metaData = getMetaData( $id );
  if ( !empty( $metaData['folder'] ) && !empty( $metaData['filename'] ) ) {
    unlink( $sourcePath . DIRECTORY_SEPARATOR . $metaData['folder'] . DIRECTORY_SEPARATOR . $metaData['filename'] );
  }

  $pdo  = newPDO();
  $stmt = $pdo->prepare( 'DELETE FROM structure WHERE rowid = :rowid' );
  $stmt->execute( ['rowid' => $id] );

  responseAjax();
}

function removeVersionControl( $taskOffset = 0 )
{
  global $taskIncomplete;
  global $taskIncompleteOffset;
  global $taskStats;
  global $sourcePath;
  global $uuidSettings;
  global $ACMS;

  if ( !empty( unserialize( $taskStats ) ) ) {
    $stats = unserialize( $taskStats );
  } else {
    $stats = ['urls' => 0, 'pages' => 0, 'replaces' => 0, 'processed' => 0, 'total' => 0];
  }

  $skipUrls = [];

  $replaceUrl = [
    '\?(?:v|ver)=[-a-f.\d]+$'                => '',
    '\?[\d]+$'                               => '',
    '\?[\w]+$'                               => '',
    '\?(?:PHPSESSID|sid)=[a-f\d]+$'          => '',
    '\?(?:PHPSESSID|sid)=[a-f\d]+&(?:amp;)?' => '?',
    '&(?:amp;)?(?:PHPSESSID|sid)=[a-f\d]+'   => '',
  ];

  $replaceCode = [
    '\?(?:v|ver)=[-a-f.\d]+([\'"\)])'        => '$1',
    '\?[\d]+([\'"\)])'                       => '$1',
    '\?[\w]+([\'"\)])'                       => '$1',
    '\?(?:PHPSESSID|sid)=[a-f\d]+([\'"])'    => '$1',
    '\?(?:PHPSESSID|sid)=[a-f\d]+&(?:amp;)?' => '?',
    '&(?:amp;)?(?:PHPSESSID|sid)=[a-f\d]+'   => '',
  ];

  $pdo = newPDO();
  if ( empty( $stats['total'] ) ) $stats['total'] = $pdo->query( "SELECT COUNT(1) FROM structure" )->fetchColumn();
  $stmt = $pdo->prepare( "SELECT rowid, * FROM structure WHERE rowid > :taskOffset ORDER BY rowid" );
  $stmt->execute( ['taskOffset' => $taskOffset] );

  while ( $url = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
    $stats['processed']++;
    if ( in_array( $url['rowid'], $skipUrls ) ) continue;

    // fix url
    $urlRemoved = false;
    foreach ( $replaceUrl as $search => $replace ) {
      preg_match_all( "~{$search}~i", rawurldecode( $url['request_uri'] ), $found );
      $matches = preg_filter( "~{$search}~i", "{$replace}", rawurldecode( $url['request_uri'] ), -1, $count );
      if ( !$count ) continue;
      $request_uri_new         = encodePath( preg_replace( "~{$search}~is", "{$replace}", rawurldecode( $url['request_uri'] ) ) );
      $request_uri_new_decoded = rawurldecode( $request_uri_new );
      $request_uri_new_valid   = substr( $request_uri_new, 0, 1 ) === '/' && filter_var( 'http://domain' . $request_uri_new, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED );

      if ( !$request_uri_new_valid ) continue;

      $url_existing = getUrlByPath( $url['hostname'], $request_uri_new );

      if ( !$url_existing ) {
        $url['original_filename'] = $url['filename'];
        $url['urlID']             = $url['rowid'];
        $url['url']               = $url['protocol'] . '://' . $url['hostname'] . $request_uri_new;
        $url['request_uri']       = $request_uri_new_decoded;
        updateUrlSettings( $url );
        $url = getMetaData( $url['rowid'] );
      } else {
        $url_existing = getUrl( $url_existing['rowid'] );
        if ( $url_existing && $url_existing['rowid'] != $url['rowid'] ) {
          if ( $url_existing['filetime'] < $url['filetime'] ) {
            removeUrl( $url_existing['rowid'] );
            $skipUrls[]               = $url_existing['rowid'];
            $url['original_filename'] = $url['filename'];
            $url['urlID']             = $url['rowid'];
            $url['url']               = $url['protocol'] . '://' . $url['hostname'] . $request_uri_new;
            $url['request_uri']       = $request_uri_new_decoded;
            updateUrlSettings( $url );
            $url = getMetaData( $url['rowid'] );
          } else {
            removeUrl( $url['rowid'] );
            $urlRemoved = true;
          }
        }
      }
      $stats['urls']++;
    }
    // end fix url

    if ( $urlRemoved ) continue;
    if ( !in_array( $url['mimetype'], ['text/html', 'text/css', 'application/javascript', 'application/x-javascript', 'text/javascript', 'text/plain', 'application/json', 'application/xml', 'text/xml'] ) ) continue;

    // fix code
    if ( $url['filename'] == '' ) continue;
    $file          = $sourcePath . DIRECTORY_SEPARATOR . $url['folder'] . DIRECTORY_SEPARATOR . $url['filename'];
    $backupCreated = false;

    foreach ( $replaceCode as $search => $replace ) {
      $search  = convertEncoding( $search, $url['charset'], 'utf-8' );
      $replace = convertEncoding( $replace, $url['charset'], 'utf-8' );
      $search  = preg_replace( '~(*BSR_ANYCRLF)\R~', "\n", $search );
      $replace = preg_replace( '~(*BSR_ANYCRLF)\R~', "\n", $replace );

      // [TODO] check if file exists
      preg_match_all( "~{$search}~i", file_get_contents( $file ), $found );
      $matches = preg_filter( "~{$search}~i", "{$replace}", file_get_contents( $file ), -1, $count );

      if ( !$count ) continue;
      $stats['replaces']++;
      if ( !$backupCreated ) backupFile( $url['rowid'], 'replace' );
      $backupCreated = true;
      file_put_contents( $file, $matches );
    }

    if ( $backupCreated ) updateFilesize( $url['rowid'], filesize( $file ) );
    // end fix code

    $stats['pages']++;
    if ( $ACMS['ACMS_TIMEOUT'] && ( microtime( true ) - ACMS_START_TIME ) > ( $ACMS['ACMS_TIMEOUT'] - 1 ) ) {
      $taskStats            = serialize( $stats );
      $taskIncomplete       = true;
      $taskIncompleteOffset = $url['rowid'];
      return $stats;
    }
  }

  if ( $stats['replaces'] ) createBackupBreakpoint( L( 'Versions in CSS and JS' ) . '. ' . sprintf( L( 'Processed: %s' ), number_format( $stats['replaces'], 0 ) ) );
  return $stats;
}

function replaceUrl( $existingId, $metaDataNew )
{
  global $sourcePath;
  global $uuidSettings;

  backupFile( $existingId, 'replace' );
  $metaDataExisting        = getMetaData( $existingId );
  $mimeNew                 = getMimeInfo( $metaDataNew['mimetype'] );
  $metaDataNew['protocol'] = !empty( $uuidSettings['https'] ) ? 'https' : 'http';
  $metaDataNew['folder']   = $mimeNew['folder'];
  $metaDataNew['filename'] = sprintf( '%s.%08d.%s', convertPathToFilename( $metaDataNew['request_uri'] ), $existingId, $mimeNew['extension'] );

  $pdo  = newPDO();
  $stmt = $pdo->prepare( 'UPDATE structure SET url = :url, request_uri = :request_uri, folder = :folder, filename = :filename, mimetype = :mimetype, charset = :charset, filesize = :filesize, filetime = :filetime, enabled = :enabled, redirect = :redirect WHERE rowid = :rowid' );
  $stmt->execute( [
    'url'          => $metaDataNew['protocol'] . '://' . $metaDataNew['hostname'] . $metaDataNew['request_uri'],
    'protocol'     => $metaDataNew['protocol'],
    'hostname'     => $metaDataNew['hostname'],
    'request_uri'  => $metaDataNew['request_uri'],
    'folder'       => $metaDataNew['folder'],
    'filename'     => $metaDataNew['filename'],
    'mimetype'     => $metaDataNew['mimetype'],
    'charset'      => $metaDataNew['charset'],
    'filesize'     => $metaDataNew['filesize'],
    'filetime'     => $metaDataNew['filetime'],
    'url_original' => $metaDataNew['url_original'],
    'enabled'      => $metaDataNew['enabled'],
    'redirect'     => $metaDataNew['redirect'],
    'rowid'        => $existingId,
  ] );

  if ( !empty( $metaDataExisting['filename'] ) ) {
    unlink( $sourcePath . DIRECTORY_SEPARATOR . $metaDataExisting['folder'] . DIRECTORY_SEPARATOR . $metaDataExisting['filename'] );
  }
  copy( $metaDataNew['tmp_file_path'], $sourcePath . DIRECTORY_SEPARATOR . $metaDataNew['folder'] . DIRECTORY_SEPARATOR . $metaDataNew['filename'] );
}

function responseAjax( $status = true )
{
  if ( !empty( $_POST['ajax'] ) ) {
    header( 'Content-Type: application/json' );
    echo json_encode( ['status' => 'ok'] );
    exit;
  }
}

function sanitizeString( $string, $length = 200, $latin = 1, $delimiter = '-' )
{
  $string = strip_tags( $string );

  if ( $latin ) {
    if ( function_exists( 'transliterator_transliterate' ) ) {
      $string = iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', transliterator_transliterate( 'Any-Latin; Latin-ASCII; NFD; [:Nonspacing Mark:] Remove; NFC; [:Punctuation:] Remove; Lower();', $string ) );
    } else {
      $char_map = array(
        // Russian
        'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh',
        'З' => 'Z', 'И' => 'I', 'Й' => 'J', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O',
        'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C',
        'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Sh', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '', 'Э' => 'E', 'Ю' => 'Yu',
        'Я' => 'Ya',
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo', 'ж' => 'zh',
        'з' => 'z', 'и' => 'i', 'й' => 'j', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
        'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c',
        'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sh', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu',
        'я' => 'ya',
        // Ukrainian
        'Є' => 'Ye', 'І' => 'I', 'Ї' => 'Yi', 'Ґ' => 'G',
        'є' => 'ye', 'і' => 'i', 'ї' => 'yi', 'ґ' => 'g',
        // Latin
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE', 'Ç' => 'C',
        'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ð' => 'D', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ő' => 'O',
        'Ø' => 'O', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ű' => 'U', 'Ý' => 'Y', 'Þ' => 'TH',
        'ß' => 'ss',
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae', 'ç' => 'c',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ð' => 'd', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ő' => 'o',
        'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ű' => 'u', 'ý' => 'y', 'þ' => 'th',
        'ÿ' => 'y',
        // Greek
        'Α' => 'A', 'Β' => 'B', 'Γ' => 'G', 'Δ' => 'D', 'Ε' => 'E', 'Ζ' => 'Z', 'Η' => 'H', 'Θ' => '8',
        'Ι' => 'I', 'Κ' => 'K', 'Λ' => 'L', 'Μ' => 'M', 'Ν' => 'N', 'Ξ' => '3', 'Ο' => 'O', 'Π' => 'P',
        'Ρ' => 'R', 'Σ' => 'S', 'Τ' => 'T', 'Υ' => 'Y', 'Φ' => 'F', 'Χ' => 'X', 'Ψ' => 'PS', 'Ω' => 'W',
        'Ά' => 'A', 'Έ' => 'E', 'Ί' => 'I', 'Ό' => 'O', 'Ύ' => 'Y', 'Ή' => 'H', 'Ώ' => 'W', 'Ϊ' => 'I',
        'Ϋ' => 'Y',
        'α' => 'a', 'β' => 'b', 'γ' => 'g', 'δ' => 'd', 'ε' => 'e', 'ζ' => 'z', 'η' => 'h', 'θ' => '8',
        'ι' => 'i', 'κ' => 'k', 'λ' => 'l', 'μ' => 'm', 'ν' => 'n', 'ξ' => '3', 'ο' => 'o', 'π' => 'p',
        'ρ' => 'r', 'σ' => 's', 'τ' => 't', 'υ' => 'y', 'φ' => 'f', 'χ' => 'x', 'ψ' => 'ps', 'ω' => 'w',
        'ά' => 'a', 'έ' => 'e', 'ί' => 'i', 'ό' => 'o', 'ύ' => 'y', 'ή' => 'h', 'ώ' => 'w', 'ς' => 's',
        'ϊ' => 'i', 'ΰ' => 'y', 'ϋ' => 'y', 'ΐ' => 'i',
        // Turkish
        'Ş' => 'S', 'İ' => 'I', 'Ç' => 'C', 'Ü' => 'U', 'Ö' => 'O', 'Ğ' => 'G',
        'ş' => 's', 'ı' => 'i', 'ç' => 'c', 'ü' => 'u', 'ö' => 'o', 'ğ' => 'g',

        // Czech
        'Č' => 'C', 'Ď' => 'D', 'Ě' => 'E', 'Ň' => 'N', 'Ř' => 'R', 'Š' => 'S', 'Ť' => 'T', 'Ů' => 'U',
        'Ž' => 'Z',
        'č' => 'c', 'ď' => 'd', 'ě' => 'e', 'ň' => 'n', 'ř' => 'r', 'š' => 's', 'ť' => 't', 'ů' => 'u',
        'ž' => 'z',
        // Polish
        'Ą' => 'A', 'Ć' => 'C', 'Ę' => 'e', 'Ł' => 'L', 'Ń' => 'N', 'Ó' => 'o', 'Ś' => 'S', 'Ź' => 'Z',
        'Ż' => 'Z',
        'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ó' => 'o', 'ś' => 's', 'ź' => 'z',
        'ż' => 'z',
        // Latvian
        'Ā' => 'A', 'Č' => 'C', 'Ē' => 'E', 'Ģ' => 'G', 'Ī' => 'i', 'Ķ' => 'k', 'Ļ' => 'L', 'Ņ' => 'N',
        'Š' => 'S', 'Ū' => 'u', 'Ž' => 'Z',
        'ā' => 'a', 'č' => 'c', 'ē' => 'e', 'ģ' => 'g', 'ī' => 'i', 'ķ' => 'k', 'ļ' => 'l', 'ņ' => 'n',
        'š' => 's', 'ū' => 'u', 'ž' => 'z',
      );
      $string   = str_replace( array_keys( $char_map ), $char_map, $string );
      $string   = iconv( 'UTF-8', 'US-ASCII//TRANSLIT//IGNORE', $string );
    }
  }

  $string = preg_replace( '~[^\p{L}\p{Nd}]+~u', $delimiter, $string );
  $string = preg_replace( '~[' . preg_quote( $delimiter, '~' ) . ']{2,}~', $delimiter, $string );
  $string = trim( $string, $delimiter );

  if ( function_exists( 'mb_strtolower' ) ) {
    $string = mb_strtolower( $string );
  }
  $string = strtolower( $string );

  if ( $length && function_exists( 'mb_substr' ) ) {
    return mb_substr( $string, 0, $length );
  }

  return $string;
}

function saveFile( $rowid )
{
  global $sourcePath;
  backupFile( $rowid, 'edit' );
  $metaData = getMetaData( $rowid );
  if ( isset( $metaData['charset'] ) ) {
    $content = convertEncoding( $_POST['content'], $metaData['charset'], 'UTF-8' );
  } else {
    $content = $_POST['content'];
  }
  file_put_contents( $sourcePath . DIRECTORY_SEPARATOR . $metaData['folder'] . DIRECTORY_SEPARATOR . $metaData['filename'], $content );
  updateFilesize( $rowid, strlen( $content ) );

  responseAjax();
}

function saveMetaData( $name, $data )
{
  createTable( 'meta' );
  $pdo  = newPDO();
  $stmt = $pdo->prepare( "INSERT OR IGNORE INTO meta (name, data) VALUES (:name, :data)" );
  $stmt->bindParam( 'name', $name, PDO::PARAM_STR );
  $stmt->bindParam( 'data', $data, PDO::PARAM_STR );
  $stmt->execute();

  $stmt = $pdo->prepare( "UPDATE meta SET data = :data WHERE name = :name" );
  $stmt->bindParam( 'name', $name, PDO::PARAM_STR );
  $stmt->bindParam( 'data', $data, PDO::PARAM_STR );
  $stmt->execute();
}

function saveTemplateFile( $name )
{
  global $sourcePath;
  createDirectory( 'templates' );
  $metaData = getTemplate( $name );
  if ( empty( $metaData ) ) return false;
  if ( isset( $metaData['charset'] ) ) {
    $content = convertEncoding( $_POST['content'], $metaData['charset'], 'UTF-8' );
  } else {
    $content = $_POST['content'];
  }
  file_put_contents( $sourcePath . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . $metaData['name'] . '.html', $content );

  responseAjax();
}

function sendRequestCloudflare( $type, $path, $data = '' )
{
  $acmsCloudflare = readMetaData( 'acms_cloudflare' );
  $options        = array(
    CURLOPT_URL            => 'https://api.cloudflare.com' . $path,
    CURLOPT_CUSTOMREQUEST  => $type,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_FAILONERROR    => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HTTPHEADER     => array(
      'Authorization: Bearer ' . $acmsCloudflare['data']['token'],
      'Content-Type: application/json',
    ),
    CURLOPT_POSTFIELDS     => $data,
  );
  $ch             = curl_init();
  curl_setopt_array( $ch, $options );
  $response = curl_exec( $ch );
  curl_close( $ch );
  $response = json_decode( $response, true );
  return $response;
}

function setAcmsSettings( $settings, $filename = null )
{
  global $sourcePath;
  global $ACMS;
  if ( empty( $sourcePath ) ) return;
  if ( empty( $filename ) ) {
    $filename = $sourcePath . DIRECTORY_SEPARATOR . '.acms.settings.json';
  }

  if ( !inSafeMode() ) {
    if ( strlen( $settings['ACMS_PASSWORD'] ) ) {
      $settings['ACMS_PASSWORD'] = password_hash( $settings['ACMS_PASSWORD'], PASSWORD_DEFAULT );
      unset( $_SESSION['archivarix.logged'] );
    } else {
      if ( !empty( $_POST['remove_password'] ) ) {
        $settings['ACMS_PASSWORD'] = '';
      } else {
        unset( $settings['ACMS_PASSWORD'] );
      }
    }

    if ( strlen( $settings['ACMS_SAFE_PASSWORD'] ) ) {
      $settings['ACMS_SAFE_PASSWORD'] = password_hash( $settings['ACMS_SAFE_PASSWORD'], PASSWORD_DEFAULT );
    } else {
      if ( !empty( $_POST['remove_safe_password'] ) ) {
        $settings['ACMS_SAFE_PASSWORD'] = '';
      } else {
        unset( $settings['ACMS_SAFE_PASSWORD'] );
      }
    }

    if ( strlen( $settings['ACMS_ALLOWED_IPS'] ) ) {
      $settings['ACMS_ALLOWED_IPS'] = preg_replace( '~[^\d./,:]~', '', $settings['ACMS_ALLOWED_IPS'] );
    }
  }

  if ( inSafeMode() ) {
    unset( $settings['ACMS_PASSWORD'] );
    unset( $settings['ACMS_SAFE_PASSWORD'] );
    unset( $settings['ACMS_ALLOWED_IPS'] );
  }

  if ( !strlen( $ACMS['ACMS_PASSWORD'] ) && strlen( ACMS_PASSWORD ) ) $settings['ACMS_PASSWORD'] = password_hash( ACMS_PASSWORD, PASSWORD_DEFAULT );
  if ( !strlen( $ACMS['ACMS_SAFE_PASSWORD'] ) && strlen( ACMS_SAFE_PASSWORD ) ) $settings['ACMS_SAFE_PASSWORD'] = password_hash( ACMS_SAFE_PASSWORD, PASSWORD_DEFAULT );
  $ACMS = array_merge( $ACMS, $settings );
  file_put_contents( $filename, json_encode( $ACMS, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
}

function setApiKey( $apiKey )
{
  global $uuidSettings;
  $pdo = newPDO();
  if ( !isset( $uuidSettings['apikey'] ) ) {
    $stmt = $pdo->prepare( "INSERT INTO settings (param, value) VALUES ('apikey', :apikey)" );
  } else {
    $stmt = $pdo->prepare( "UPDATE settings SET value = :apikey WHERE param = 'apikey'" );
  }
  $stmt->bindParam( ':apikey', $apiKey, PDO::PARAM_STR );
  $stmt->execute();
}

function setCloudflareToken( $zone_id, $token )
{
  $options = array(
    CURLOPT_URL            => 'https://api.cloudflare.com/client/v4/zones/' . $zone_id,
    CURLOPT_CUSTOMREQUEST  => 'GET',
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_FAILONERROR    => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HTTPHEADER     => array(
      'Authorization: Bearer ' . $token,
      'Content-Type:application/json',
    ),
  );
  $ch      = curl_init();
  curl_setopt_array( $ch, $options );
  $response = curl_exec( $ch );
  curl_close( $ch );
  $response = json_decode( $response, true );
  if ( !empty( $response['success'] ) ) {
    saveMetaData( 'acms_cloudflare', json_encode( ['zone_id' => $response['result']['id'], 'name' => $response['result']['name'], 'token' => $token] ) );
    return true;
  }
}

function setDevelopmentModeCloudflare( $enable = 1 )
{
  $acmsCloudflare = readMetaData( 'acms_cloudflare' );
  $path           = '/client/v4/zones/' . $acmsCloudflare['data']['zone_id'] . '/settings/development_mode';
  $data           = $enable ? '{"value":"on"}' : '{"value":"off"}';
  $response       = sendRequestCloudflare( 'PATCH', $path, $data );
  if ( !empty( $response['success'] ) ) {
    $acmsCloudflare = readMetaData( 'acms_cloudflare' );
    if ( $enable ) {
      $acmsCloudflare['data']['dev_mode_time_remaining'] = time() + $response['result']['time_remaining'];
    } else {
      unset( $acmsCloudflare['data']['dev_mode_time_remaining'] );
    }
    saveMetaData( 'acms_cloudflare', json_encode( $acmsCloudflare['data'] ) );
    return true;
  }
}

function setLoaderSettings( $settings, $filename = null )
{
  global $sourcePath;
  $LOADER = loadLoaderSettings();
  if ( empty( $filename ) ) {
    $filename = $sourcePath . DIRECTORY_SEPARATOR . '.loader.settings.json';
  }
  $includeCustom = [];
  if ( !empty( $settings['ARCHIVARIX_INCLUDE_CUSTOM']['FILE'] ) ) foreach ( $settings['ARCHIVARIX_INCLUDE_CUSTOM']['FILE'] as $index => $value ) {
    if ( !strlen( basename( $settings['ARCHIVARIX_INCLUDE_CUSTOM']['FILE'][$index] ) ) ) continue;
    if ( !strlen( $settings['ARCHIVARIX_INCLUDE_CUSTOM']['KEYPHRASE'][$index] ) ) continue;
    $includeCustom[] = [
      'FILE'      => basename( $settings['ARCHIVARIX_INCLUDE_CUSTOM']['FILE'][$index] ),
      'KEYPHRASE' => $settings['ARCHIVARIX_INCLUDE_CUSTOM']['KEYPHRASE'][$index],
      'LIMIT'     => $settings['ARCHIVARIX_INCLUDE_CUSTOM']['LIMIT'][$index],
      'REGEX'     => $settings['ARCHIVARIX_INCLUDE_CUSTOM']['REGEX'][$index],
      'POSITION'  => $settings['ARCHIVARIX_INCLUDE_CUSTOM']['POSITION'][$index],
    ];
  }
  $settings['ARCHIVARIX_INCLUDE_CUSTOM'] = $includeCustom;
  $LOADER                                = array_merge( $LOADER, $settings );
  file_put_contents( $filename, json_encode( $LOADER, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
}

function showWarning()
{
  global $warnings;
  if ( !isset( $warnings ) ) {
    $warnings = array();
  }
  foreach ( $warnings as $warning ) {
    echo <<< EOT
<div class="toast mw-100" role="alert" aria-live="assertive" aria-atomic="true" data-autohide="false" data-delay="5000" data-show="true">
  <div class="toast-header text-light bg-{$warning['level']}">
    <strong class="mr-auto">{$warning['title']}</strong>
    <small class="text-light"></small>
    <button type="button" class="ml-2 mb-1 close text-light" data-dismiss="toast" aria-label="Close">
      <span aria-hidden="true">&times;</span>
    </button>
  </div>
  <div class="toast-body">
    {$warning['message']}
  </div>
</div>
EOT;
  }
}

function updateCustomFile( $input )
{
  global $sourcePath;
  if ( inSafeMode() && preg_match( '~[<]([?%]|[^>]*script\b[^>]*\blanguage\b[^>]*\bphp\b)~is', $input['content'] ) ) {
    addWarning( L( 'You cannot create or edit custom files with a php code under a safe mode.' ), 4, L( 'Custom Files' ) );
    return false;
  }
  $includesPath = $sourcePath . DIRECTORY_SEPARATOR . 'includes';
  $filename     = basename( $input['filename'] );
  if ( !file_exists( $includesPath . DIRECTORY_SEPARATOR . $filename ) ) return;
  $newFilename = basename( $input['new_filename'] );
  if ( !preg_match( '~^[-.\w]+$~i', $newFilename ) || in_array( $newFilename, ['.', '..'] ) ) $newFilename = $filename;
  unlink( $includesPath . DIRECTORY_SEPARATOR . $filename );
  $file = $includesPath . DIRECTORY_SEPARATOR . $newFilename;
  file_put_contents( $file, $input['content'] );
  return true;
}

function updateExternalLinks( $setAttributes = [], $taskOffset = 0 )
{
  global $taskIncomplete;
  global $taskIncompleteOffset;
  global $taskStats;
  global $sourcePath;
  global $uuidSettings;
  global $ACMS;

  if ( !empty( unserialize( $taskStats ) ) ) {
    $stats = unserialize( $taskStats );
  } else {
    $stats = ['links' => 0, 'pages' => 0, 'processed' => 0, 'total' => 0];
  }
  if ( function_exists( 'libxml_use_internal_errors' ) ) libxml_use_internal_errors( true );
  $pdo = newPDO();
  if ( empty( $stats['total'] ) ) $stats['total'] = $pdo->query( "SELECT COUNT(1) FROM structure WHERE mimetype = 'text/html'" )->fetchColumn();
  $stmt = $pdo->prepare( "SELECT rowid, * FROM structure WHERE mimetype = 'text/html' AND rowid > :taskOffset ORDER BY rowid" );
  $stmt->execute( ['taskOffset' => $taskOffset] );
  while ( $url = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
    $updatedLinks = 0;
    $file         = $sourcePath . DIRECTORY_SEPARATOR . $url['folder'] . DIRECTORY_SEPARATOR . $url['filename'];
    if ( !is_file( $file ) ) continue;
    $html = file_get_contents( $file );
    if ( !strlen( $html ) ) continue;
    $html = convertEncoding( convertHtmlEncoding( $html, 'utf-8', $url['charset'] ), 'html-entities', 'utf-8' );
    unset( $dom );
    $dom                      = new DOMDocument();
    $dom->formatOutput        = true;
    $dom->documentURI         = $url['url'];
    $dom->strictErrorChecking = false;
    $dom->encoding            = 'utf-8';
    $dom->loadHTML( $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
    $linkTags = $dom->getElementsByTagName( 'a' );
    for ( $n = $linkTags->length - 1; $n >= 0; --$n ) {
      $hrefAttribute     = $linkTags->item( $n )->getAttribute( 'href' );
      $hrefAbsolute      = rawurldecode( getAbsolutePath( $url['url'], $hrefAttribute ) );
      $hrefHostname      = strtolower( convertIdnToAscii( parse_url( $hrefAbsolute, PHP_URL_HOST ) ) );
      $attributesUpdated = 0;
      if ( !preg_match( '~^([-a-z0-9.]+\.)?' . preg_quote( $uuidSettings['domain'], '~' ) . '$~i', $hrefHostname ) ) {
        foreach ( $setAttributes as $attributeName => $attributeValue ) {
          if ( empty( $attributeValue ) ) continue;
          $linkTags->item( $n )->setAttribute( $attributeName, $attributeValue );
          $attributesUpdated++;
        }
        if ( $attributesUpdated ) $updatedLinks++;
      }
    }
    if ( $updatedLinks ) {
      backupFile( $url['rowid'], 'edit' );
      file_put_contents( $file, convertHtmlEncoding( html_entity_decode( $dom->saveHTML() ), $url['charset'], 'utf-8' ) );
      updateFilesize( $url['rowid'], filesize( $file ) );
      $stats['links'] += $updatedLinks;
    }
    $stats['pages']++;
    $stats['processed']++;

    if ( $ACMS['ACMS_TIMEOUT'] && ( microtime( true ) - ACMS_START_TIME ) > ( $ACMS['ACMS_TIMEOUT'] - 1 ) ) {
      $taskStats            = serialize( $stats );
      $taskIncomplete       = true;
      $taskIncompleteOffset = $url['rowid'];
      return $stats;
    }
  }
  if ( $stats['links'] ) createBackupBreakpoint( L( 'Update external links' ) . '. ' . sprintf( L( 'Processed: %s' ), number_format( $stats['links'], 0 ) ) );
  return ( $stats );
}

function updateFilesize( $rowid, $filesize )
{
  $pdo  = newPDO();
  $stmt = $pdo->prepare( "UPDATE structure SET filesize = :filesize WHERE rowid = :rowid" );
  $stmt->execute( ['filesize' => $filesize, 'rowid' => $rowid] );
}

function updateHtaccessFile( $content )
{
  global $sourcePath;
  // [TODO] validator: create tmpdir, save index.html, .htaccess, do curl, response code != 500
  $htaccessPath = __DIR__ . DIRECTORY_SEPARATOR . '.htaccess';
  file_put_contents( $htaccessPath, $content );
}

function updateSystem()
{
  global $uuidSettings;

  $cmsVersion = ACMS_VERSION;
  $updateInfo = json_decode( file_get_contents( 'https://archivarix.com/' . $_SESSION['archivarix.lang'] . '/cms/?ver=' . $cmsVersion . '&uuid=' . $uuidSettings['uuid'] ), true );
  if ( empty( $updateInfo['cms_version'] ) || empty( $updateInfo['loader_version'] ) ) {
    addWarning( 'Could not connect to the update server.', 4, L( 'System update' ) );
    return;
  }
  $loaderInfo = getLoaderInfo();

  if ( version_compare( $updateInfo['cms_version'], $cmsVersion, '>' ) ) {
    $cmsFileZip   = tempnam( getTempDirectory(), 'archivarix.' );
    $cmsLocalFile = $_SERVER['SCRIPT_FILENAME'];
    downloadFile( $updateInfo['cms_download_link'], $cmsFileZip );
    $zip = new ZipArchive();
    $zip->open( $cmsFileZip );
    $cmsData = $zip->getFromName( 'archivarix.cms.php' );
    if ( !empty( $cmsData ) && file_put_contents( $cmsLocalFile, $cmsData ) ) {
      addWarning( sprintf( L( '%s updated from version %s to %s. Click on the menu logo to reload page into the new version.' ), L( 'Archivarix CMS' ), $cmsVersion, $updateInfo['cms_version'] ), 1, L( 'System update' ) );
    } else {
      addWarning( sprintf( L( 'Could not update %s. Please update manually.' ), L( 'Archivarix CMS' ) ), 4, L( 'System update' ) );
    }
    $zip->close();
  } else {
    addWarning( sprintf( L( 'You already have the latest version %s of %s.' ), $cmsVersion, L( 'Archivarix CMS' ) ), 2, L( 'System update' ) );
  }

  if ( empty( $loaderInfo['filename'] ) ) {
    addWarning( sprintf( L( '%s could not be detected. Please update manually.' ), L( 'Archivarix Loader' ) ), 3, L( 'System update' ) );
    return;
  }

  if ( version_compare( $updateInfo['loader_version'], $loaderInfo['version'], '>' ) ) {
    $loaderFileZip   = tempnam( getTempDirectory(), 'archivarix.' );
    $loaderLocalFile = __DIR__ . DIRECTORY_SEPARATOR . $loaderInfo['filename'];
    downloadFile( $updateInfo['loader_download_link'], $loaderFileZip );
    $zip = new ZipArchive();
    $zip->open( $loaderFileZip );
    $loaderData = $zip->getFromName( 'index.php' );
    if ( !empty( $loaderData ) && file_put_contents( $loaderLocalFile, $loaderData ) ) {
      addWarning( sprintf( L( '%s updated from version %s to %s.' ), L( 'Archivarix Loader' ), $loaderInfo['version'], $updateInfo['loader_version'] ), 1, L( 'System update' ) );
    } else {
      addWarning( sprintf( L( 'Could not update %s. Please update manually' ), L( 'Archivarix Loader' ) ), 4, L( 'System update' ) );
    }
    $zip->close();
  } else {
    addWarning( sprintf( L( 'You already have the latest version %s of %s.' ), $loaderInfo['version'], L( 'Archivarix Loader' ) ), 2, L( 'System update' ) );
  }

}

function updateTemplateSettings( $input )
{
  global $sourcePath;
  $input['name'] = strtolower( $input['name'] );
  if ( $input['name'] != $input['name_orig'] ) {
    if ( !empty( getTemplate( $input['name'] ) ) ) {
      addWarning( sprintf( L( "Template with a name %s already exists." ), $input['name'] ), 3, L( 'Templates' ) );
      return false;
    }
    rename( $sourcePath . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . $input['name_orig'] . '.html', $sourcePath . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . $input['name'] . '.html' );
  }
  $pdo  = newPDO();
  $stmt = $pdo->prepare( "UPDATE templates SET name = :name, hostname = :hostname, mimetype = :mimetype, charset = :charset, uploads = :uploads, path = :path WHERE name = :name_orig" );
  $stmt->execute( [
    'name'      => $input['name'],
    'hostname'  => $input['hostname'],
    'mimetype'  => $input['mimetype'],
    'charset'   => $input['charset'],
    'uploads'   => $input['uploads'],
    'path'      => $input['path'],
    'name_orig' => $input['name_orig'],
  ] );
}

function updateUrlEncoded( $taskOffset = 0 )
{
  global $taskIncomplete;
  global $taskIncompleteOffset;
  global $taskStats;
  global $sourcePath;
  global $ACMS;

  if ( !empty( unserialize( $taskStats ) ) ) {
    $stats = unserialize( $taskStats );
  } else {
    $stats = ['links' => 0, 'pages' => 0, 'processed' => 0, 'total' => 0];
  }
  if ( function_exists( 'libxml_use_internal_errors' ) ) libxml_use_internal_errors( true );
  $pdo = newPDO();
  if ( empty( $stats['total'] ) ) $stats['total'] = $pdo->query( "SELECT COUNT(1) FROM structure WHERE mimetype = 'text/html'" )->fetchColumn();
  $stmt = $pdo->prepare( "SELECT rowid, * FROM structure WHERE mimetype = 'text/html' AND rowid > :taskOffset ORDER BY rowid" );
  $stmt->execute( ['taskOffset' => $taskOffset] );
  while ( $url = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
    $updatedUrls = 0;
    $file        = $sourcePath . DIRECTORY_SEPARATOR . $url['folder'] . DIRECTORY_SEPARATOR . $url['filename'];
    if ( !is_file( $file ) ) continue;
    $html = file_get_contents( $file );
    if ( !strlen( $html ) ) continue;
    $html = convertEncoding( convertHtmlEncoding( $html, 'utf-8', $url['charset'] ), 'html-entities', 'utf-8' );
    unset( $dom );
    $dom                      = new DOMDocument();
    $dom->formatOutput        = true;
    $dom->documentURI         = $url['url'];
    $dom->strictErrorChecking = false;
    $dom->encoding            = 'utf-8';
    $dom->loadHTML( $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
    $attributesUpdated = 0;

    $linkTags = $dom->getElementsByTagName( 'a' );
    for ( $n = $linkTags->length - 1; $n >= 0; --$n ) {
      $hrefAttribute = $linkTags->item( $n )->getAttribute( 'href' );
      if ( preg_match( '~%([0-9A-F][a-f]|[a-f][0-9A-Z]|[a-f]{2})~', $hrefAttribute ) ) {
        $hrefAttribute = preg_replace_callback( '~(%[0-9a-f]{2})~', function ( $matches ) {
          return strtoupper( $matches[0] );
        }, $hrefAttribute );
        $attributesUpdated++;
        $updatedUrls++;
        $linkTags->item( $n )->setAttribute( 'href', $hrefAttribute );
      }
    }

    $linkTags = $dom->getElementsByTagName( 'img' );
    for ( $n = $linkTags->length - 1; $n >= 0; --$n ) {
      $srcAttribute = $linkTags->item( $n )->getAttribute( 'src' );
      if ( preg_match( '~%([0-9A-F][a-f]|[a-f][0-9A-Z]|[a-f]{2})~', $srcAttribute ) ) {
        $srcAttribute = preg_replace_callback( '~(%[0-9a-f]{2})~', function ( $matches ) {
          return strtoupper( $matches[0] );
        }, $srcAttribute );
        $attributesUpdated++;
        $updatedUrls++;
        $linkTags->item( $n )->setAttribute( 'src', $srcAttribute );
      }
    }

    if ( $updatedUrls ) {
      backupFile( $url['rowid'], 'edit' );
      file_put_contents( $file, convertHtmlEncoding( html_entity_decode( $dom->saveHTML() ), $url['charset'], 'utf-8' ) );
      updateFilesize( $url['rowid'], filesize( $file ) );
      $stats['links'] += $updatedUrls;
    }
    $stats['pages']++;
    $stats['processed']++;

    if ( $ACMS['ACMS_TIMEOUT'] && ( microtime( true ) - ACMS_START_TIME ) > ( $ACMS['ACMS_TIMEOUT'] - 1 ) ) {
      $taskStats            = serialize( $stats );
      $taskIncomplete       = true;
      $taskIncompleteOffset = $url['rowid'];
      return $stats;
    }
  }
  if ( $stats['links'] ) createBackupBreakpoint( L( 'Broken URLencoded links' ) . '. ' . sprintf( L( 'Processed: %s' ), number_format( $stats['links'], 0 ) ) );
  return ( $stats );
}

function updateUrlFromUpload( $params, $file )
{
  if ( !$file['tmp_name'] ) exit;

  global $sourcePath;
  backupFile( $params['urlID'], 'upload' );
  $pdo      = newPDO();
  $metaData = getMetaData( $params['urlID'] );

  $mime             = getMimeInfo( $file['type'] );
  $uplMimeType      = $file['type'];
  $uplFileSize      = filesize( $file['tmp_name'] );
  $uplFileExtension = $mime['extension'];
  $uplFileName      = sprintf( '%s.%08d.%s', convertPathToFilename( $metaData['request_uri'] ), $metaData['rowid'], $uplFileExtension );

  unlink( $sourcePath . DIRECTORY_SEPARATOR . $metaData['folder'] . DIRECTORY_SEPARATOR . $metaData['filename'] );
  move_uploaded_file( $file['tmp_name'], $sourcePath . DIRECTORY_SEPARATOR . $mime['folder'] . DIRECTORY_SEPARATOR . $uplFileName );

  $stmt = $pdo->prepare( "UPDATE structure SET folder = :folder, filename = :filename, mimetype = :mimetype, filesize = :filesize WHERE rowid = :rowid" );
  $stmt->execute( [
    'folder'   => $mime['folder'],
    'filename' => $uplFileName,
    'mimetype' => $uplMimeType,
    'filesize' => $uplFileSize,
    'rowid'    => $metaData['rowid'],
  ] );
  exit;
}

function updateUrlSettings( $settings )
{
  global $sourcePath;
  backupFile( $settings['urlID'], 'settings' );
  $metaData = getMetaData( $settings['urlID'] );

  if ( encodePath( $settings['request_uri'] ) == $metaData['request_uri'] ) {
    $settings['filename'] = $metaData['filename'];
  } else {
    $mime = getMimeInfo( $settings['mimetype'] );
    rename( $sourcePath . DIRECTORY_SEPARATOR . $metaData['folder'] . DIRECTORY_SEPARATOR . $metaData['filename'], $sourcePath . DIRECTORY_SEPARATOR . $mime['folder'] . DIRECTORY_SEPARATOR . sprintf( '%s.%08d.%s', convertPathToFilename( $settings['request_uri'] ), $metaData['rowid'], $mime['extension'] ) );
    $settings['filename'] = sprintf( '%s.%08d.%s', convertPathToFilename( $settings['request_uri'] ), $metaData['rowid'], $mime['extension'] );
  }

  $pdo  = newPDO();
  $stmt = $pdo->prepare( 'UPDATE structure SET url = protocol || "://" || hostname || :request_uri, request_uri = :request_uri, filename = :filename, mimetype = :mimetype, charset = :charset, enabled = :enabled, redirect = :redirect, filetime = :filetime WHERE rowid = :rowid' );

  $stmt->execute( [
    'rowid'       => $settings['urlID'],
    'request_uri' => encodePath( $settings['request_uri'] ),
    'filename'    => $settings['filename'],
    'mimetype'    => $settings['mimetype'],
    'charset'     => $settings['charset'],
    'enabled'     => $settings['enabled'],
    'redirect'    => encodeUrl( $settings['redirect'] ),
    'filetime'    => $settings['filetime'],
  ] );
}

function updateViewport( $params, $taskOffset = 0 )
{
  global $taskIncomplete;
  global $taskIncompleteOffset;
  global $taskStats;
  global $sourcePath;
  global $ACMS;

  $customViewport = !empty( trim( $params['custom_viewport'] ) ) ? $params['custom_viewport'] : 'width=device-width, initial-scale=1';
  $overwrite      = !empty( $params['overwrite'] ) ? 1 : 0;

  if ( !empty( unserialize( $taskStats ) ) ) {
    $stats = unserialize( $taskStats );
  } else {
    $stats = ['links' => 0, 'pages' => 0, 'processed' => 0, 'total' => 0];
  }
  if ( function_exists( 'libxml_use_internal_errors' ) ) libxml_use_internal_errors( true );
  $pdo = newPDO();
  if ( empty( $stats['total'] ) ) $stats['total'] = $pdo->query( "SELECT COUNT(1) FROM structure WHERE mimetype = 'text/html'" )->fetchColumn();
  $stmt = $pdo->prepare( "SELECT rowid, * FROM structure WHERE mimetype = 'text/html' AND rowid > :taskOffset ORDER BY rowid" );
  $stmt->execute( ['taskOffset' => $taskOffset] );
  while ( $url = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
    $updatedUrls = 0;
    $createTag   = 0;
    $file        = $sourcePath . DIRECTORY_SEPARATOR . $url['folder'] . DIRECTORY_SEPARATOR . $url['filename'];
    if ( !is_file( $file ) ) continue;
    $html = file_get_contents( $file );
    if ( !strlen( $html ) ) continue;
    $html = convertEncoding( convertHtmlEncoding( $html, 'utf-8', $url['charset'] ), 'html-entities', 'utf-8' );
    unset( $dom );
    $dom                      = new DOMDocument();
    $dom->formatOutput        = true;
    $dom->documentURI         = $url['url'];
    $dom->strictErrorChecking = false;
    $dom->encoding            = 'utf-8';
    $dom->loadHTML( $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

    $metaTags = $dom->getElementsByTagName( 'meta' );
    for ( $n = $metaTags->length - 1; $n >= 0; --$n ) {
      $nameAttribute    = $metaTags->item( $n )->getAttribute( 'name' );
      $contentAttribute = $metaTags->item( $n )->getAttribute( 'content' );
      if ( strtolower( $nameAttribute ) == 'viewport' ) {
        if ( $overwrite && strtolower( $contentAttribute ) != strtolower( $customViewport ) ) {
          $metaTags->item( $n )->setAttribute( 'content', $customViewport );
          $updatedUrls++;
        } else {
          $createTag = 1;
        }
      }
    }


    if ( !$createTag && !$updatedUrls ) {
      $metaTag = $dom->createElement( 'meta' );
      $metaTag->setAttribute( 'name', 'viewport' );
      $metaTag->setAttribute( 'content', $customViewport );
      if ( $metaTags->length ) {
        $metaTags->item( 0 )->parentNode->insertBefore( $metaTag, $metaTags->item( 0 ) );
      } else {
        $headTag = $dom->getElementsByTagName( 'head' );
        if ( $headTag->length ) {
          if ( $headTag->item( 0 )->hasChildNodes() ) {
            $headTag->item( 0 )->insertBefore( $metaTag, $headTag->item( 0 )->firstChild );
          } else {
            $headTag->item( 0 )->appendChild( $metaTag );
          }
        }
      }
      $updatedUrls++;
    }


    if ( $updatedUrls ) {
      backupFile( $url['rowid'], 'edit' );
      file_put_contents( $file, convertHtmlEncoding( html_entity_decode( $dom->saveHTML() ), $url['charset'], 'utf-8' ) );
      updateFilesize( $url['rowid'], filesize( $file ) );
      $stats['links']++;
    }
    $stats['pages']++;
    $stats['processed']++;

    if ( $ACMS['ACMS_TIMEOUT'] && ( microtime( true ) - ACMS_START_TIME ) > ( $ACMS['ACMS_TIMEOUT'] - 1 ) ) {
      $taskStats            = serialize( $stats );
      $taskIncomplete       = true;
      $taskIncompleteOffset = $url['rowid'];
      return $stats;
    }
  }
  if ( $stats['links'] ) createBackupBreakpoint( L( 'Viewport meta tag' ) . '. ' . sprintf( L( 'Processed: %s' ), number_format( $stats['links'], 0 ) ) );
  return ( $stats );
}

function uploadAcmsJson( $file )
{
  global $ACMS;
  global $sourcePath;
  if ( !isset( $file['error'] ) || $file['error'] > 0 ) return;
  $settings = json_decode( file_get_contents( $file['tmp_name'] ), true );
  if ( json_last_error() !== JSON_ERROR_NONE ) return;
  if ( !is_array( $settings ) && !count( $settings ) ) return;
  $settings = array_filter( $settings, function ( $k ) {
    return preg_match( '~^ACMS_~i', $k );
  }, ARRAY_FILTER_USE_KEY );
  $ACMS     = array_merge( $ACMS, $settings );
  $filename = $sourcePath . DIRECTORY_SEPARATOR . '.acms.settings.json';
  file_put_contents( $filename, json_encode( $ACMS, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
  return true;
}

function uploadCustomFile( $file )
{
  global $sourcePath;
  if ( inSafeMode() && preg_match( '~[<]([?%]|[^>]*script\b[^>]*\blanguage\b[^>]*\bphp\b)~is', file_get_contents( $file['tmp_name'] ) ) ) return false;
  $includesPath = $sourcePath . DIRECTORY_SEPARATOR . 'includes';
  createDirectory( 'includes' );
  if ( empty( $file['name'] ) || empty( basename( $file['name'] ) ) ) {
    $mimeInfo     = getMimeInfo( $file['type'] );
    $file['name'] = date( 'Y-m-d_H-m-s' ) . '.' . $mimeInfo['extension'];
  }
  move_uploaded_file( $file['tmp_name'], $includesPath . DIRECTORY_SEPARATOR . basename( $file['name'] ) );
}

function uploadImport( $file )
{
  $importFolder = createDirectory( 'imports' );
  $importInfo   = getImportInfo( $file['tmp_name'] );
  if ( $importInfo ) {
    move_uploaded_file( $file['tmp_name'], $importFolder . DIRECTORY_SEPARATOR . $importInfo['info']['settings']['uuid'] . ".zip" );
    return $importInfo['info']['settings']['uuid'];
  }
  return;
}

function uploadLoaderJson( $file )
{
  global $sourcePath;
  if ( !isset( $file['error'] ) || $file['error'] > 0 ) return;
  $settings = json_decode( file_get_contents( $file['tmp_name'] ), true );
  if ( json_last_error() !== JSON_ERROR_NONE ) return;
  if ( !is_array( $settings ) && !count( $settings ) ) return;
  $settings = array_filter( $settings, function ( $k ) {
    return preg_match( '~^ARCHIVARIX_~i', $k );
  }, ARRAY_FILTER_USE_KEY );
  if ( !empty( $settings['ARCHIVARIX_CUSTOM_FILES'] ) ) {
    foreach ( $settings['ARCHIVARIX_CUSTOM_FILES'] as $customFile ) {
      createCustomFile( ['filename' => $customFile['filename'], 'content' => base64_decode( $customFile['content'] )] );
    }
  }
  unset( $settings['ARCHIVARIX_CUSTOM_FILES'] );
  $LOADER   = loadLoaderSettings();
  $LOADER   = array_merge( $LOADER, $settings );
  $filename = $sourcePath . DIRECTORY_SEPARATOR . '.loader.settings.json';
  file_put_contents( $filename, json_encode( $LOADER, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
  return true;
}

function urlExists( $urls )
{
  $pdo = newPDO();
  if ( is_array( $urls ) ) {
    $sqlVariants    = '';
    $sqlVariantsArr = [];
    foreach ( $urls as $key => $url ) {
      $sqlVariants                  .= " OR url = :url_{$key} ";
      $sqlVariantsArr["url_{$key}"] = $url;
    }
    $stmt = $pdo->prepare( "SELECT rowid FROM structure WHERE 0 {$sqlVariants} LIMIT 1" );
    $stmt->execute(
      $sqlVariantsArr
    );
  } else {
    $stmt = $pdo->prepare( "SELECT rowid FROM structure WHERE url = :url LIMIT 1" );
    $stmt->execute( [
      'url' => $urls,
    ] );
  }
  $id = $stmt->fetchColumn();
  if ( $id ) {
    return $id;
  }
}

function compare( $string1, $string2, $compareCharacters = false )
{
  $start = 0;
  if ( $compareCharacters ) {
    $sequence1 = $string1;
    $sequence2 = $string2;
    $end1      = strlen( $string1 ) - 1;
    $end2      = strlen( $string2 ) - 1;
  } else {
    $sequence1 = preg_split( '~(*BSR_ANYCRLF)\R~', $string1 );
    $sequence2 = preg_split( '~(*BSR_ANYCRLF)\R~', $string2 );
    $end1      = count( $sequence1 ) - 1;
    $end2      = count( $sequence2 ) - 1;
  }

  while ( $start <= $end1 && $start <= $end2
    && $sequence1[$start] == $sequence2[$start] ) {
    $start++;
  }

  while ( $end1 >= $start && $end2 >= $start
    && $sequence1[$end1] == $sequence2[$end2] ) {
    $end1--;
    $end2--;
  }

  $table = computeTable( $sequence1, $sequence2, $start, $end1, $end2 );

  $partialDiff = generatePartialDiff( $table, $sequence1, $sequence2, $start );

  $diff = array();
  for ( $index = 0; $index < $start; $index++ ) {
    $diff[] = array($sequence1[$index], 0);
  }
  while ( count( $partialDiff ) > 0 ) $diff[] = array_pop( $partialDiff );
  for ( $index = $end1 + 1;
        $index < ( $compareCharacters ? strlen( $sequence1 ) : count( $sequence1 ) );
        $index++ ) {
    $diff[] = array($sequence1[$index], 0);
  }

  return $diff;
}


function compareFiles( $file1, $file2, $compareCharacters = false )
{
  return compare(
    file_get_contents( $file1 ),
    file_get_contents( $file2 ),
    $compareCharacters );
}

function computeTable( $sequence1, $sequence2, $start, $end1, $end2 )
{
  $length1 = $end1 - $start + 1;
  $length2 = $end2 - $start + 1;
  $table   = array(array_fill( 0, $length2 + 1, 0 ));
  for ( $index1 = 1; $index1 <= $length1; $index1++ ) {
    $table[$index1] = array(0);
    for ( $index2 = 1; $index2 <= $length2; $index2++ ) {
      if ( $sequence1[$index1 + $start - 1]
        == $sequence2[$index2 + $start - 1] ) {
        $table[$index1][$index2] = $table[$index1 - 1][$index2 - 1] + 1;
      } else {
        $table[$index1][$index2] =
          max( $table[$index1 - 1][$index2], $table[$index1][$index2 - 1] );
      }
    }
  }
  return $table;
}

function generatePartialDiff( $table, $sequence1, $sequence2, $start )
{
  $diff   = array();
  $index1 = count( $table ) - 1;
  $index2 = count( $table[0] ) - 1;
  while ( $index1 > 0 || $index2 > 0 ) {
    if ( $index1 > 0 && $index2 > 0
      && $sequence1[$index1 + $start - 1]
      == $sequence2[$index2 + $start - 1] ) {
      $diff[] = array($sequence1[$index1 + $start - 1], 0);
      $index1--;
      $index2--;
    } elseif ( $index2 > 0
      && $table[$index1][$index2] == $table[$index1][$index2 - 1] ) {
      $diff[] = array($sequence2[$index2 + $start - 1], 2);
      $index2--;
    } else {
      $diff[] = array($sequence1[$index1 + $start - 1], 1);
      $index1--;
    }
  }
  return $diff;
}

function compareToHTML( $diff, $separator = '<br>' )
{
  $html = '';
  foreach ( $diff as $line ) {
    switch ( $line[1] ) {
      case 0 :
        //continue 2; // no unchanged
        $element = 'span';
        break;
      case 1    :
        $element = 'del';
        break;
      case 2   :
        $element = 'ins';
        break;
    }
    $html .=
      '<' . $element . '>'
      . htmlspecialchars( $line[0] )
      . '</' . $element . '>';
    $html .= $separator;
  }
  return $html;
}

// START
$section = !empty( $section ) ? $section : null;

if ( $sourcePath === false ) {
  $dsn                     = 'sqlite::memory:';
  $uuidSettings            = ['uuid' => '', 'domain' => ''];
  $section                 = 'install';
  $integrationPrerequisite = checkIntegrationPrerequisite();
} else {
  $dsn          = getDSN();
  $uuidSettings = getSettings();
}

if ( $ACMS['ACMS_DISABLE_HISTORY'] ) {
  if ( !empty( getBackups( 'stats' )['total'] ) ) {
    deleteBackup( ['all' => 1] );
  }
}

if ( !$extensionsAllowed ) $section = '';

$urlOffsets           = !empty( $_POST['urlOffsets'] ) ? unserialize( $_POST['urlOffsets'] ) : array();
$urlsTotal            = array();
$taskIncompleteOffset = !empty( $_POST['taskOffset'] ) ? $_POST['taskOffset'] : 0;
$taskStats            = !empty( $_POST['taskStats'] ) ? $_POST['taskStats'] : serialize( false );

if ( $section == 'install' && !isset( $_POST['action'] ) ) {
  clearstatcache();
  $missingExtensions = getMissingExtensions( ['curl', 'json', 'pdo_sqlite', 'zip'] );

  if ( !is_writable( __DIR__ ) ) {
    $noProceed = true;
    addWarning( L( 'The working directory does not have writing permissions.' ) . ' ' . ( fileowner( __DIR__ ) == 0 || filegroup( __DIR__ ) == 0 ? L( 'It belongs to the "root" level user. Please fix directory/files owner and group, not permissions.' ) : '' ), 4, L( 'System check' ) );
  }
  if ( !in_array( 'pdo_sqlite', $missingExtensions ) && version_compare( getSqliteVersion(), '3.7.0', '<' ) ) {
    $noProceed = true;
    addWarning( sprintf( L( 'Installation can only work with SQLite version 3.7.0 or newer. Your pdo_sqlite uses version %s that is very outdated.' ), getSqliteVersion() ), 4, L( 'Initial installation' ) );
  }
  if ( !empty( $missingExtensions ) ) {
    $noProceed = true;
    addWarning( sprintf( L( 'This tool requires following PHP extensions to be installed: %s.' ), implode( ', ', $missingExtensions ) ), 4, L( 'Initial installation' ) );
  }
  addWarning( L( 'Website is missing or not installed yet.' ) . ' ' . L( 'Please upload files to your hosting or use the form below to import/upload an existing restore.' ), 2, L( 'Initial installation' ) );
}

if ( $section == 'install' && isset( $_POST['action'] ) && checkXsrf() ) {
  switch ( $_POST['action'] ) {
    case 'download.serial.install' :
      $sourcePath = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . '.content.tmp';
      if ( !file_exists( $sourcePath ) ) {
        mkdir( $sourcePath, 0777, true );
      }
      $uuid = downloadFromSerial( $_POST['uuid'], $taskIncompleteOffset );
      if ( $uuid ) {
        addWarning( sprintf( L( 'Restore file %s downloaded.' ), $uuid ), 1, L( 'Import tool' ) );
      } else {
        addWarning( L( 'Could not download restore file.' ), 4, L( 'Import tool' ) );
      }
      $sourcePath = false;
      break;
    case 'import.upload.install' :
      $sourcePath = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . '.content.tmp';
      if ( !file_exists( $sourcePath ) ) {
        mkdir( $sourcePath, 0777, true );
      }
      if ( !empty( $_FILES['import_file'] ) && !$_FILES['import_file']['error'] ) {
        $uuid = uploadImport( $_FILES['import_file'] );
        if ( $uuid ) {
          addWarning( sprintf( L( 'Restore file %s downloaded.' ), $uuid ), 1, L( 'Import tool' ) );
        } else {
          addWarning( L( 'Uploaded .zip file has incorrect structure' ), 4, L( 'Import tools' ) );
        }
      }
      $sourcePath = false;
      break;
    case 'import.remove.install' :
      $sourcePath = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . '.content.tmp';
      if ( !empty( $_POST['filename'] ) && removeImport( $_POST['filename'] ) ) {
        addWarning( sprintf( L( 'Restore file %s removed.' ), $_POST['filename'] ), 1, L( 'Import tool' ) );
      }
      $sourcePath = false;
      break;
    case 'import.perform.install' :
      if ( $taskIncompleteOffset == 0 ) {
        $sourcePath     = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . '.content.tmp';
        $importFileName = $sourcePath . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . $_POST['filename'];
        $import         = getImportInfo( $sourcePath . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . $_POST['filename'] );
        $sourcePath     = createStructure( $import );
        rename( $import['zip_path'], $sourcePath . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . $import['filename'] );
        $importSettings              = $_POST['settings'];
        $importSettings['overwrite'] = 'all';
        $dsn                         = getDSN();
        $uuidSettings                = getSettings();
        deleteDirectory( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . '.content.tmp' . DIRECTORY_SEPARATOR );
        $_POST['action']   = 'import.perform';
        $_POST['settings'] = $importSettings;
        putLoader( __DIR__ );
        loadAcmsSettings();
      }
      break;
  }
}

if ( isset( $_POST['action'] ) &&
  $accessAllowed &&
  $extensionsAllowed &&
  $sourcePath &&
  checkXsrf() ) {
  if ( !inSafeMode() ) {
    switch ( $_POST['action'] ) {
      case 'api.key.generate' :
        setApiKey( getRandomString( 32 ) );
        addWarning( L( 'New API key is set.' ), 1, L( 'API key' ) );
        $uuidSettings = getSettings();
        $section      = 'tools';
        break;
      case 'api.key.remove' :
        if ( removeApiKey() ) {
          addWarning( L( 'API key removed.' ), 1, L( 'API key' ) );
          $uuidSettings = getSettings();
        } else {
          addWarning( L( 'Could not remove API key.' ), 4, L( 'API key' ) );
        }
        $section = 'tools';
        break;
      case 'download.acms.json' :
        header( "Content-Type: application/json" );
        header( "Content-disposition: attachment; filename=\"acms.settings.json\"" );
        echo json_encode( loadAcmsSettings(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        exit;
        break;
      case 'upload.acms.json' :
        if ( uploadAcmsJson( $_FILES['file'] ) ) {
          addWarning( L( 'New settings applied.' ) . '<br>' . L( 'If settings had a password you will see a login form on the next click.' ), 1, L( 'Settings' ) );
        } else {
          addWarning( L( 'Could not apply the file.' ), 4, L( 'Settings' ) );
        }
        $section = 'settings';
        break;
      case 'download.loader.json' :
        header( "Content-Type: application/json" );
        header( "Content-disposition: attachment; filename=\"loader.settings.json\"" );
        echo packLoaderSettings();
        exit;
        break;
      case 'upload.loader.json' :
        if ( uploadLoaderJson( $_FILES['file'] ) ) {
          addWarning( L( 'New settings applied.' ), 1, L( 'Settings' ) );
        } else {
          addWarning( L( 'Could not apply the file.' ), 4, L( 'Settings' ) );
        }
        $section    = 'settings';
        $subSection = 'loader';
        break;
      case 'fun.convert.website' :
        funConvertWebsite();
        addWarning( L( 'Done.' ), 1, L( 'Flat-File version' ) );
        $section = 'tools';
        break;
      case 'fun.import.md' :
        //funImportMD( $_POST['params'], $taskIncompleteOffset );
        addWarning( L( 'Done.' ), 1 );
        $section = 'tools';
        break;
      case 'edit.htaccess' :
        if ( !empty( $_POST['htaccess'] ) ) {
          updateHtaccessFile( $_POST['htaccess'] );
          addWarning( sprintf( L( 'File %s updated successfully.' ), '.htaccess' ), 1, L( '.htaccess editor' ) );
        }
        $section = 'htaccess';
        break;
    }
  }
  switch ( $_POST['action'] ) {
    case 'settings.view' :
      $section = 'settings';
      break;
    case 'set.loader.settings' :
      setLoaderSettings( $_POST['settings'] );
      addWarning( L( 'Settings were updated.' ), 1, L( 'Settings' ) );
      $section    = 'settings';
      $subSection = 'loader';
      break;
    case 'delete.custom.file' :
      if ( deleteCustomFile( $_POST['filename'] ) ) {
        addWarning( sprintf( L( 'File %s deleted.' ), $_POST['filename'] ), 1, L( 'Custom Files' ) );
      } else {
        addWarning( sprintf( L( 'Could not delete file %s.' ), $_POST['filename'] ), 4, L( 'Custom Files' ) );
      }
      $section    = 'settings';
      $subSection = 'custom';
      break;
    case 'edit.custom.file' :
      $customFileMeta = getCustomFileMeta( $_POST['filename'] );
      $section        = 'settings';
      $subSection     = 'custom';
      break;
    case 'create.custom.file' :
      if ( createCustomFile( $_POST ) ) {
        addWarning( sprintf( L( 'File %s created successfully.' ), $_POST['filename'] ), 1, L( 'Custom Files' ) );
      } else {
        addWarning( sprintf( L( 'Could not create file %s.' ), $_POST['filename'] ), 4, L( 'Custom Files' ) );
      }
      $section    = 'settings';
      $subSection = 'custom';
      break;
    case 'update.custom.file' :
      if ( updateCustomFile( $_POST ) ) {
        addWarning( sprintf( L( 'File %s updated successfully.' ), $_POST['filename'] ), 1, L( 'Custom Files' ) );
      } else {
        addWarning( sprintf( L( 'Could not update file %s.' ), $_POST['filename'] ), 4, L( 'Custom Files' ) );
      }
      $section    = 'settings';
      $subSection = 'custom';
      break;
    case 'upload.custom.file' :
      if ( !empty( $_FILES['file'] ) && !$_FILES['file']['error'] ) {
        uploadCustomFile( $_FILES['file'] );
        exit;
      }
      $section    = 'settings';
      $subSection = 'custom';
      break;
    case 'update.system' :
      updateSystem();
      break;
    case 'update.url.settings' :
      updateUrlSettings( $_POST );
      break;
    case 'update.url.content' :
      saveFile( $_POST['urlID'] );
      break;
    case 'update.url.upload' :
      updateUrlFromUpload( $_POST, $_FILES['file'] );
      exit;
      break;
    case 'download.url' :
      $metaData = getMetaData( $_POST['urlID'] );
      header( 'Content-Type: application/octet-stream' );
      header( 'Content-Disposition: attachment; filename=' . pathinfo( $metaData['request_uri'], PATHINFO_BASENAME ) );
      header( 'Expires: 0' );
      header( 'Cache-Control: must-revalidate' );
      header( 'Pragma: public' );
      header( 'Content-Length: ' . filesize( $sourcePath . DIRECTORY_SEPARATOR . $metaData['folder'] . DIRECTORY_SEPARATOR . $metaData['filename'] ) );
      readfile( $sourcePath . DIRECTORY_SEPARATOR . $metaData['folder'] . DIRECTORY_SEPARATOR . $metaData['filename'] );
      exit;
      break;
    case 'remove.url' :
      removeUrl( $_POST['urlID'] );
      break;
    case 'clone.url' :
      $cloneID = cloneUrl( $_POST['urlID'], $_POST['cloneUrlPath'] );
      if ( $cloneID ) {
        $_POST['urlID'] = $cloneID;
        $_POST['show']  = 'edit.url';
      }
      break;
    case 'create.url' :
      $createID = createUrl( $_POST );
      if ( $createID ) {
        $_POST['urlID'] = $createID;
        $_POST['show']  = 'edit.url';
      }
      break;
    case 'searchreplace.code' :
      $searchResults = doSearchReplaceCode( $_POST, $taskIncompleteOffset );
      if ( empty( $searchResults ) && $_POST['type'] != 'new' && !isset( $_POST['perform'] ) ) {
        addWarning( L( 'No matches found' ), 4, L( 'Search & Replace' ) );
      }
      if ( $_POST['type'] == 'replace' && isset( $_POST['perform'] ) && $_POST['perform'] == 'replace' ) {
        addWarning( L( 'All replaces have been written to files!' ), 1, L( 'Search & Replace' ) );
      }
      $section = 'search';
      break;
    case 'searchreplace.url' :
      $searchResults = doSearchReplaceUrls( $_POST, $taskIncompleteOffset );
      if ( empty( $searchResults ) && $_POST['type'] != 'new' && !isset( $_POST['perform'] ) ) {
        addWarning( L( 'No matches found' ), 4, L( 'Search & Replace' ) );
      }
      if ( $_POST['type'] == 'replace' && isset( $_POST['perform'] ) && $_POST['perform'] == 'replace' ) {
        addWarning( L( 'All replaces have been written to files!' ), 1, L( 'Search & Replace' ) );
      }
      $section = 'search';
      break;
    case 'history.edit' :
      $backups = getBackups();
      if ( empty( $backups ) ) addWarning( L( 'No backups found.' ), 2, L( 'History' ) );
      if ( !empty( $backups ) ) addWarning( sprintf( L( 'Current backups take %s of space. Don\'t forget to disable or purge all backups if you don\'t need them.' ), getHumanSize( getDirectorySize( $sourcePath . DIRECTORY_SEPARATOR . 'backup' ) ) ), 2, L( 'History' ) );
      if ( $ACMS['ACMS_DISABLE_HISTORY'] ) addWarning( L( 'Backups are disabled in CMS settings.' ), 3, L( 'History' ) );
      $section = 'backups';
      break;
    case 'history.recover' :
      recoverBackup( $_POST, $taskIncompleteOffset );
      $backups = getBackups();
      if ( empty( $backups ) ) addWarning( L( 'No backups found.' ), 2, L( 'History' ) );
      if ( !empty( $backups ) ) addWarning( sprintf( L( 'Current backups take %s of space. Don\'t forget to disable or purge all backups if you don\'t need them.' ), getHumanSize( getDirectorySize( $sourcePath . DIRECTORY_SEPARATOR . 'backup' ) ) ), 2, L( 'History' ) );
      if ( $ACMS['ACMS_DISABLE_HISTORY'] ) addWarning( L( 'Backups are disabled in CMS settings.' ), 3, L( 'History' ) );
      $section = 'backups';
      break;
    case 'history.purge' :
      deleteBackup( $_POST );
      $backups = getBackups();
      if ( empty( $backups ) ) addWarning( L( 'No backups found.' ), 2, L( 'History' ) );
      if ( !empty( $backups ) ) addWarning( sprintf( L( 'Current backups take %s of space. Don\'t forget to disable or purge all backups if you don\'t need them.' ), getHumanSize( getDirectorySize( $sourcePath . DIRECTORY_SEPARATOR . 'backup' ) ) ), 2, L( 'History' ) );
      if ( $ACMS['ACMS_DISABLE_HISTORY'] ) addWarning( L( 'Backups are disabled in CMS settings.' ), 3, L( 'History' ) );
      $section = 'backups';
      break;
    case 'history.breakpoint.create' :
      if ( createBackupBreakpoint( $_POST['name'] ) ) addWarning( L( 'A new breakpoint created.' ), 1, L( 'History' ) );
      $backups = getBackups();
      $section = 'backups';
      break;
    case 'missing.clear' :
      clearMissingUrls();
      addWarning( L( 'The list has been cleared.' ), 1, L( 'Missing URLs' ) );
      $section = 'missing.urls';
      break;
    case 'missing.show' :
      $missingUrls = getMissingUrls();
      if ( empty( $missingUrls ) ) addWarning( L( 'No missing URLs were caught during visitors website browsing.' ), 2, L( 'Missing URLs' ) );
      $LOADER = loadLoaderSettings();
      if ( !$LOADER['ARCHIVARIX_CATCH_MISSING'] ) addWarning( L( 'Looks like this option is not enabled in Loader\'s Settings.' ), 3, L( 'Missing URLs' ) );
      $section = 'missing.urls';
      break;
    case 'change.page' :
      $urlOffsets[$_POST['domain']] = $_POST['page'];
      break;
    case 'tools.view' :
      $section = 'tools';
      break;
    case 'convert.utf8' :
      $converted = convertUTF8( $taskIncompleteOffset );
      addWarning( sprintf( L( 'Conversion of %d files to %s is complete.' ), $converted, 'UTF-8' ), 1, L( 'Website conversion to UTF-8' ) );
      $section = 'tools';
      break;
    case 'broken.encoded.urls' :
      $updatedUrls = updateUrlEncoded( $taskIncompleteOffset );
      addWarning( sprintf( L( 'Updated %d encoded URLs in %d different pages.' ), $updatedUrls['links'], $updatedUrls['pages'] ), 1, L( 'Broken URLencoded links' ) );
      $section = 'tools';
      break;
    case 'update.viewport' :
      $updatedUrls = updateViewport( $_POST, $taskIncompleteOffset );
      addWarning( sprintf( L( 'Viewport set in %d different pages.' ), $updatedUrls['links'] ), 1, L( 'Viewport meta tag' ) );
      $section = 'tools';
      break;
    case 'broken.links.remove' :
      $brokenLinks = removeBrokenLinks( $taskIncompleteOffset );
      addWarning( sprintf( L( 'Removed %d broken internal links in %d different pages.' ), $brokenLinks['links'], $brokenLinks['pages'] ), 1, L( 'Remove broken links' ) );
      $section = 'tools';
      break;
    case 'broken.images.remove' :
      $brokenImages = removeBrokenImages( $taskIncompleteOffset );
      addWarning( sprintf( L( 'Removed %d broken internal images in %d different pages.' ), $brokenImages['images'], $brokenImages['pages'] ), 1, L( 'Remove broken images' ) );
      $section = 'tools';
      break;
    case 'external.links.update' :
      $updatedLinks = updateExternalLinks( $_POST['attributes'], $taskIncompleteOffset );
      addWarning( sprintf( L( 'Updated %d external links in %d different pages.' ), $updatedLinks['links'], $updatedLinks['pages'] ), 1, L( 'Work with external links' ) );
      $section = 'tools';
      break;
    case 'external.links.remove' :
      $removedLinks = removeExternalLinks( $taskIncompleteOffset );
      addWarning( sprintf( L( 'Removed %d external links in %d different pages.' ), $removedLinks['links'], $removedLinks['pages'] ), 1, L( 'Work with external links' ) );
      $section = 'tools';
      break;
    case 'version.control.remove' :
      $removedVersionControl = removeVersionControl( $taskIncompleteOffset );
      addWarning( sprintf( L( 'Fixed %d URLs, performed %d replaces in %d different pages.' ), $removedVersionControl['urls'], $removedVersionControl['replaces'], $removedVersionControl['pages'] ), 1, L( 'Versions in CSS and JS' ) );
      $section = 'tools';
      break;
    case 'import.view' :
      $section = 'import';
      break;
    case 'import.remove' :
      if ( !empty( $_POST['filename'] ) && removeImport( $_POST['filename'] ) ) {
        addWarning( sprintf( L( 'Restore file %s removed.' ), $_POST['filename'] ), 1, L( 'Import tool' ) );
      }
      $section = 'import';
      break;
    case 'import.perform' :
      if ( importPerform( $_POST['filename'], $_POST['settings'], $taskIncompleteOffset ) ) {
        addWarning( L( 'Import completed successfully.' ) . '<br>' . L( 'If the import contained a settings file with a password, then you will see a login form on the next click.' ), 1, L( 'Import tool' ) );
      }
      $section = 'import';
      break;
    case 'import.perform.reinstall' :
      $importFileName = $sourcePath . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . $_POST['filename'];
      $import         = getImportInfo( $importFileName );
      if ( empty( $import ) ) break;
      deleteBackup( ['all' => 1] );
      deleteDirectory( $sourcePath . DIRECTORY_SEPARATOR . 'html' );
      deleteDirectory( $sourcePath . DIRECTORY_SEPARATOR . 'binary' );
      createDirectory( 'html' );
      createDirectory( 'binary' );
      dropTable( 'structure' );
      createTable( 'structure' );
      dropTable( 'settings' );
      createTable( 'settings' );
      $pdo = newPDO();
      foreach ( $import['info']['settings'] as $param => $value ) {
        $stmt = $pdo->prepare( "INSERT INTO settings VALUES(:param, :value)" );
        $stmt->execute( ['param' => $param, 'value' => $value] );
      }
      $importSettings['overwrite'] = 'all';
      $importSettings['hostnames'] = array_keys( $import['info']['hostnames'] );
      $uuidSettings                = getSettings();
      loadAcmsSettings();
      $_POST['action']          = 'import.perform';
      $_POST['settings']        = $importSettings;
      $_POST['disable_history'] = 1;
      importPerform( $_POST['filename'], $importSettings, $taskIncompleteOffset );
      $section = 'main';
      break;
    case 'download.serial' :
      $uuid = downloadFromSerial( $_POST['uuid'], $taskIncompleteOffset );
      if ( $uuid ) {
        addWarning( sprintf( L( 'Restore file %s downloaded.' ), $uuid ), 1, L( 'Import tool' ) );
      } else {
        addWarning( L( 'Could not download restore file.' ), 4, L( 'Import tool' ) );
      }
      $section = 'import';
      break;
    case 'import.upload' :
      if ( !empty( $_FILES['import_file'] ) && !$_FILES['import_file']['error'] ) {
        uploadImport( $_FILES['import_file'] );
      }
      $section = 'import';
      break;
    case 'create.template' :
      $templateId = createTemplateFromPage( $_POST['name'], $_POST['urlID'] );
      if ( $templateId ) {
        addWarning( sprintf( L( 'Template %s created.' ), $templateId ), 1, L( 'Templates' ) );
        $_POST['show'] = 'edit.template';
        $_POST['name'] = $templateId;
      } else addWarning( sprintf( L( 'Could not create template %s.' ), htmlspecialchars( $_POST['name'] ) ), 4, L( 'Templates' ) );
      $section = 'template';
      break;
    case 'robots.allow' :
      createRobotsTxt( $_POST );
      addWarning( L( 'Website crawling has been allowed in robots.txt.' ), 1, L( 'Allow website in robots.txt' ) );
      $section = 'tools';
      break;
    case 'template.remove' :
      if ( removeTemplate( $_POST['name'] ) ) {
        addWarning( sprintf( L( 'Template %s removed.' ), $_POST['name'] ), 1, L( 'Templates' ) );
      } else {
        addWarning( sprintf( L( 'Could not remove the template %s.' ), $_POST['name'] ), 4, L( 'Templates' ) );
      }
      $section = 'template';
      break;
    case 'update.template.content' :
      saveTemplateFile( $_POST['name'] );
      addWarning( sprintf( L( 'Template %s updated.' ), $_POST['name'] ), 1, L( 'Templates' ) );
      $section = 'template';
      break;
    case 'update.template.settings' :
      updateTemplateSettings( $_POST );
      addWarning( sprintf( L( 'Template %s updated.' ), $_POST['name'] ), 1, L( 'Templates' ) );
      $_POST['show'] = 'edit.template';
      $section       = 'template';
      break;
    case 'show.create.page' :
      $template     = getTemplate( $_POST['name'] );
      $templateInfo = getTemplateInfo( $_POST['name'] );
      $section      = 'page';
      break;
    case 'create.template.page' :
      $_POST['urlID'] = createTemplatePage( $_POST );
      $_POST['show']  = 'edit.url';
      //$section = 'page';
      break;
    case 'api.cloudflare.create' :
      if ( setCloudflareToken( $_POST['zone_id'], $_POST['token'] ) ) addWarning( L( 'Access successfully saved.' ), 1, 'Cloudflare' );
      else addWarning( L( 'API token is not working.' ), 4, 'Cloudflare' );
      $section = 'tools';
      break;
    case 'api.cloudflare.remove' :
      removeMetaTableData( 'acms_cloudflare' );
      addWarning( L( 'API token removed.' ), 1, 'Cloudflare' );
      $section = 'tools';
      break;
    case 'api.cloudflare.development.mode' :
      if ( setDevelopmentModeCloudflare( $_POST['params']['enable'] ) ) addWarning( L( 'Development Mode setting is updated.' ), 1, 'Cloudflare' );
      else addWarning( L( 'Could not set Development Mode.' ), 4, 'Cloudflare' );
      $section = 'tools';
      break;
    case 'api.cloudflare.purge.cache' :
      if ( purgeCacheCloudflare() ) addWarning( L( 'Cache purged.' ), 1, 'Cloudflare' );
      else addWarning( L( 'Could not purge cache.' ), 4, 'Cloudflare' );
      $section = 'tools';
      break;
  }
}

$domains = $sourcePath === false ? [] : getAllDomains();

define( 'ACMS_ORIGINAL_DOMAIN', $uuidSettings['domain'] );

$filterValue      = null;
$metaData         = null;
$content          = null;
$documentBaseUrl  = null;
$documentCharset  = null;
$documentID       = null;
$documentMimeType = empty( $documentMimeType ) ? null : $documentMimeType;

if ( $section == 'settings' ) {
  $LOADER = loadLoaderSettings();
}

if ( isset( $_POST['filterValue'] ) ) {
  $filterValue = $_POST['filterValue'];
}

if ( isset( $_POST['show'], $_POST['urlID'] ) && $_POST['show'] == 'edit.url' ) {
  $metaData = getMetaData( $_POST['urlID'] );
  // [TODO] ignore protocol in the next release
  $realUrl      = ( ( !empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443 ) ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . $metaData['request_uri'];
  $content      = file_get_contents( $sourcePath . DIRECTORY_SEPARATOR . $metaData['folder'] . DIRECTORY_SEPARATOR . $metaData['filename'] );
  $documentPath = pathinfo( parse_url( $metaData['request_uri'], PHP_URL_PATH ), PATHINFO_DIRNAME );
  // [TODO] ignore protocol in the next release
  $documentBaseUrl  = ( ( !empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443 ) ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . $documentPath . ( $documentPath == '/' ? '' : '/' );
  $documentMimeType = $metaData['mimetype'];
  $documentCharset  = $metaData['charset'];
  $documentID       = $metaData['rowid'];
}

if ( isset( $_POST['show'], $_POST['name'] ) && $_POST['show'] == 'edit.template' ) {
  $section          = 'template';
  $template         = getTemplate( strtolower( $_POST['name'] ) );
  $template         = array_merge( $template, getTemplateInfo( $template['name'] ) );
  $documentBaseUrl  = ( ( !empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443 ) ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . '/';
  $documentMimeType = $template['mimetype'];
}

if ( !empty( $taskIncomplete ) ) {
  $taskIncompleteParams               = $_POST;
  $taskIncompleteParams['taskOffset'] = $taskIncompleteOffset;
  $taskIncompleteParams['taskStats']  = $taskStats;
} else {
  $taskIncomplete       = false;
  $taskIncompleteOffset = 0;
  $taskIncompleteParams = [];
  $taskStats            = serialize( false );
}
if ( empty( $section ) && empty( $documentID ) && $accessAllowed && $extensionsAllowed ) {
  $section = 'main';
  checkSourceStructure();
}
?>
<!doctype html>
<html lang="<?=$_SESSION['archivarix.lang']?>">
<head>
  <title>Archivarix CMS</title>
  <meta name="robots" content="noindex,nofollow">
  <meta name="referrer" content="no-referrer">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <link rel="icon" href="data:image/svg+xml;base64,PHN2ZyBpZD0iTGF5ZXJfMSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB2aWV3Qm94PSIwIDAgMTYgMTYiPjxzdHlsZT4uc3Qwe2ZpbGw6I2ZmYTcwMH0uc3Qxe2ZpbGw6I2ZmZn08L3N0eWxlPjxwYXRoIGNsYXNzPSJzdDAiIGQ9Ik0wIDBoMTZ2MTZIMHoiIGlkPSJCRyIvPjxwYXRoIGNsYXNzPSJzdDEiIGQ9Ik00LjkgMy45Yy40LS4xLjgtLjIgMS4xLS4yLjMgMCAuNi4xLjkuMy4yLjEuNC4zLjYuNy4xLjIuMy43LjUgMS4zbDEuMSAzYy4yLjcuNSAxLjIuOCAxLjYuMy40LjUuNy43LjkuMi4yLjQuMy43LjQuMi4xLjQuMS42LjFoLjR2LjFjLS4zLjEtLjUuMS0uOC4xLS4zIDAtLjUtLjEtLjgtLjItLjMtLjEtLjUtLjMtLjgtLjYtLjYtLjQtMS0xLjItMS40LTIuMmwtLjMtMUg1LjhsLS42IDEuNnYuMmMwIC4xIDAgLjEuMS4ycy4yLjEuMy4xaC4xdi4xSDMuOHYtLjFoLjFjLjEgMCAuMyAwIC40LS4xLjEtLjEuMi0uMi4zLS40bDIuMi01LjJjLS4zLS41LS43LS43LTEuMi0uN2gtLjd6bTEgNGgyLjJsLS43LTJjLS4xLS40LS4zLS43LS40LTFsLTEuMSAzeiIgaWQ9IkxvZ28iLz48L3N2Zz4=">
  <style>
    body {
      font-family: 'Open Sans', sans-serif;
      padding-top: 50px;
    }

    @media (min-width: 992px) {
      #sidebar {
        overflow-y: scroll;
        padding: 0;
        position: fixed;
        top: 60px;
        left: 0;
        bottom: 0;
      }
    }

    .bg-image {
      background: repeating-linear-gradient(45deg, #bcbcbc, #bcbcbc 10px, #f0f0f0 10px, #f0f0f0 20px);
    }

    .mce-fullscreen {
      padding-top: 55px !important;
    }

    #textarea_html {
      min-height: 400px;
      display: none;
      white-space: pre-wrap;
      word-wrap: break-word;
    }

    .CodeMirror {
      box-sizing: border-box;
      margin: 0;
      display: block;
      width: 100%;
      height: auto !important;
      padding: 0;
      font-size: 12px;
      line-height: 1.42857143;
      color: #555;
      background-color: #fff;
      background-image: none;
      border: 1px solid #ccc;
      border-radius: 4px;
      box-shadow: inset 0 1px 1px rgba(0, 0, 0, .075);
      transition: border-color ease-in-out .15s, box-shadow ease-in-out .15s;
      font-family: monospace, monospace;
    }

    .CodeMirror-focused {
      border-color: #66afe9;
      outline: 0;
      box-shadow: inset 0 1px 1px rgba(0, 0, 0, .075), 0 0 8px rgba(102, 175, 233, .6);
      transition: border-color ease-in-out .15s, box-shadow ease-in-out .15s;
    }

    .jstree-default a {
      white-space: normal !important;
      height: auto;
    }

    .jstree-anchor {
      height: auto !important;
      font-size: 0.8em;
    }

    .jstree-leaf a {
      height: auto !important;
    }

    .jstree-default a.jstree-search {
      color: inherit;
    }

    .jstree-wholerow-clicked {
      background: inherit !important;
    }

    pre {
      white-space: pre-wrap;
    }

    .domain-name-toggle[aria-expanded=false] {
      text-decoration-line: underline;
      text-decoration-style: dotted;
    }

    table.dataTable td.select-checkbox::before {
      display: none !important;
      top: inherit !important;
    }

    table.dataTable td.select-checkbox::after {
      top: inherit !important;
      left: inherit !important;
    }

    .expand-label[data-toggle="collapse"][aria-expanded="true"] .fas:before {
      content: "\f0d7";
    }

    .expand-label[data-toggle="collapse"][aria-expanded="false"].collapsed .fas:before {
      content: "\f0da";
    }

    .bg-search-url {
      background-color: #e3f1ff !important;
    }

    .dropzone {
      border: dashed 1px !important;
      text-align: center;
    }

    .dz-drag-hover {
      background-color: #28a745 !important;
    }

    .logo-cloudflare {
      height: 1em;
      background: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxMDg2LjMgMTU0LjMiPjxwYXRoIGQ9Ik0xMDgwLjIgMTA5LjdhNi4wNiA2LjA2IDAgMCAxLTYuMS02LjEgNi4xIDYuMSAwIDEgMSA2LjEgNi4xbTAtMTFhNC45IDQuOSAwIDEgMCA0LjkgNC45IDQuOTEgNC45MSAwIDAgMC00LjktNC45bTMuMSA4LjFoLTEuNGwtMS4yLTIuM2gtMS42djIuM2gtMS4zdi02LjdoMy4yYTIuMTMgMi4xMyAwIDAgMSAyLjMgMi4yIDIuMDggMi4wOCAwIDAgMS0xLjQgMnptLTIuNC0zLjVhLjk0Ljk0IDAgMCAwIDEtMWMwLS44LS40LTEtMS0xaC0ydjJ6bS02MzYuMi02LjRoMTUuNnY0Mi41aDI3LjFWMTUzaC00Mi43em01OC44IDI4LjJ2LS4xYzAtMTYuMSAxMy0yOS4yIDMwLjMtMjkuMnMzMC4xIDEyLjkgMzAuMSAyOXYuMmMwIDE2LjEtMTMgMjkuMi0zMC4zIDI5LjJzLTMwLjEtMTMtMzAuMS0yOS4xbTQ0LjYgMHYtLjFjMC04LjEtNS44LTE1LjEtMTQuNC0xNS4xcy0xNC4yIDYuOS0xNC4yIDE1di4yYzAgOC4xIDUuOCAxNS4xIDE0LjMgMTUuMXMxNC4zLTcgMTQuMy0xNS4xbTM0LjkgMy4zVjk2LjloMTUuOHYzMS4yYzAgOC4xIDQuMSAxMS45IDEwLjMgMTEuOXMxMC4zLTMuNyAxMC4zLTExLjVWOTYuOWgxNS44VjEyOGMwIDE4LjEtMTAuMyAyNi0yNi4zIDI2cy0yNS45LTgtMjUuOS0yNS42bTc2LTMxLjVoMjEuN2MyMCAwIDMxLjcgMTEuNSAzMS43IDI3Ljd2LjJjMCAxNi4yLTExLjggMjguMi0zMiAyOC4yaC0yMS4zbC0uMS01Ni4xem0yMS45IDQyLjNjOS4zIDAgMTUuNS01LjEgMTUuNS0xNC4ydi0uMmMwLTktNi4yLTE0LjItMTUuNS0xNC4yaC02LjN2MjguNWw2LjMuMXptNTQtNDIuM2g0NC45djEzLjdoLTI5LjR2OS41SDc3N1YxMzNoLTI2LjZ2MjBoLTE1LjV6bTY2LjUgMGgxNS41djQyLjVoMjcuMlYxNTNoLTQyLjd6bTgzLjMtLjRoMTVsMjMuOSA1Ni41aC0xNi43bC00LjEtMTBoLTIxLjZsLTQgMTBoLTE2LjNsMjMuOC01Ni41em0xMy43IDM0LjRsLTYuMi0xNS45LTYuMyAxNS45em00NS4yLTM0aDI2LjVjOC42IDAgMTQuNSAyLjIgMTguMyA2LjEgMy4zIDMuMiA1IDcuNSA1IDEzLjF2LjJjMCA4LjYtNC42IDE0LjMtMTEuNSAxNy4ybDEzLjQgMTkuNmgtMThsLTExLjMtMTdoLTYuOHYxN2gtMTUuNWwtLjEtNTYuMnptMjUuOCAyN2M1LjMgMCA4LjMtMi42IDguMy02LjZ2LS4zYzAtNC40LTMuMi02LjYtOC40LTYuNmgtMTAuMnYxMy41em00Ni40LTI3aDQ1LjF2MTMuM2gtMjkuN3Y4LjRoMjYuOXYxMi4zaC0yNi45djguOWgzMC4xVjE1M2gtNDUuNXptLTYwNS4xIDM0LjhhMTMuNzkgMTMuNzkgMCAwIDEtMTIuOCA4LjRjLTguNSAwLTE0LjMtNy4xLTE0LjMtMTUuMXYtLjJjMC04LjEgNS43LTE1IDE0LjItMTVhMTMuOTMgMTMuOTMgMCAwIDEgMTMuMyA5LjNoMTYuNGMtMi42LTEzLjQtMTQuNC0yMy4zLTI5LjYtMjMuMy0xNy4zIDAtMzAuMyAxMy4xLTMwLjMgMjkuMnYuMmMwIDE2LjEgMTIuOCAyOSAzMC4xIDI5IDE0LjggMCAyNi40LTkuNiAyOS40LTIyLjRsLTE2LjQtLjF6IiBmaWxsPSIjNDA0MDQxIi8+PHBhdGggZD0iTTMxMi44IDkxLjZMMjY5LjkgNjdsLTcuNC0zLjJMODcgNjUuMXY4OWwyMjUuOC4xeiIgZmlsbD0iI2ZmZiIvPjxwYXRoIGQ9Ik0yMzQuNyAxNDZjMi4xLTcuMiAxLjMtMTMuOC0yLjItMTguNy0zLjItNC41LTguNi03LjEtMTUuMS03LjRsLTEyMy4xLTEuNmEyLjI4IDIuMjggMCAwIDEtMS45LTEgMi43NiAyLjc2IDAgMCAxLS4zLTIuMiAzLjM4IDMuMzggMCAwIDEgMi45LTIuMmwxMjQuMi0xLjZjMTQuNy0uNyAzMC43LTEyLjYgMzYuMy0yNy4ybDcuMS0xOC41YTQgNCAwIDAgMCAuMi0yLjQgODAuODcgODAuODcgMCAwIDAtMTU1LjUtOC4zIDM2LjM1IDM2LjM1IDAgMCAwLTU4IDI1LjQgMzguMTggMzguMTggMCAwIDAgLjkgMTIuN0E1MS42NiA1MS42NiAwIDAgMCAwIDE0NC43YTYzIDYzIDAgMCAwIC41IDcuNSAyLjQ2IDIuNDYgMCAwIDAgMi40IDIuMWgyMjcuMmEzLjA5IDMuMDkgMCAwIDAgMi45LTIuMmwxLjctNi4xeiIgZmlsbD0iI2YzODAyMCIvPjxwYXRoIGQ9Ik0yNzMuOSA2Ni45Yy0xLjEgMC0yLjMgMC0zLjQuMWEyIDIgMCAwIDAtMS44IDEuNGwtNC44IDE2LjdjLTIuMSA3LjItMS4zIDEzLjggMi4yIDE4LjcgMy4yIDQuNSA4LjYgNy4xIDE1LjEgNy40bDI2LjIgMS42YTIuMjggMi4yOCAwIDAgMSAxLjkgMSAyLjgyIDIuODIgMCAwIDEgLjMgMi4yIDMuMzggMy4zOCAwIDAgMS0yLjkgMi4ybC0yNy4zIDEuNmMtMTQuOC43LTMwLjcgMTIuNi0zNi4zIDI3LjJsLTIgNS4xYTEuNDQgMS40NCAwIDAgMCAxLjQgMmg5My44YTIuNDYgMi40NiAwIDAgMCAyLjQtMS44IDY4LjM1IDY4LjM1IDAgMCAwIDIuNS0xOC4yIDY3LjI0IDY3LjI0IDAgMCAwLTY3LjMtNjcuMiIgZmlsbD0iI2ZhYWU0MCIvPjwvc3ZnPg==');
      background-repeat: no-repeat;
      background-position: center;
    }

    .revisions ins {
      border-top: 1px solid #b8ecc6;
      border-left: 1px solid #b8ecc6;
      border-right: 1px solid #b8ecc6;
      background: #e6ffed;
      padding: 0 5px 0 20px;
      display: block;
      text-decoration: none;
      min-height: 1.5em;
      white-space: pre-wrap;
      position: relative;
    }

    .revisions ins:hover {
      background: #cdffd8;
    }

    .revisions ins::before {
      content: "+";
      color: #6c757d;
      background: #cdffd8;
      padding: 0 5px 0 5px;
      position: absolute;
      top: 0;
      bottom: 0;
      left: 0;
    }

    .revisions ins:last-child {
      border-bottom: 1px solid #b8ecc6;
    }

    .revisions del {
      border-top: 1px solid #f5d5d9;
      border-left: 1px solid #f5d5d9;
      border-right: 1px solid #f5d5d9;
      background: #ffeef0;
      padding: 0 5px 0 20px;
      display: block;
      text-decoration: none;
      min-height: 1.5em;
      white-space: pre-wrap;
      position: relative;
    }

    .revisions del:hover {
      background: #ffdce0;
    }

    .revisions del::before {
      content: "-";
      color: #6c757d;
      background: #ffdce0;
      padding: 0 5px 0 5px;
      position: absolute;
      top: 0;
      bottom: 0;
      left: 0;
    }

    .revisions del:last-child {
      border-bottom: 1px solid #f5d5d9;
    }

    .revisions span {
      border-top: 1px solid #e2e2e2;
      border-left: 1px solid #e2e2e2;
      border-right: 1px solid #e2e2e2;
      background: #ffffff;
      padding: 0 5px 0 20px;
      display: none;
      text-decoration: none;
      min-height: 1.5em;
      white-space: pre-wrap;
      position: relative;
    }

    .revisions span:hover {
      background: #eeeeee;
    }

    .revisions span::before {
      content: " ";
      color: #6c757d;
      background: #f1f1f1;
      padding: 0 5px 0 5px;
      position: absolute;
      top: 0;
      bottom: 0;
      left: 0;
    }

    .revisions span:last-child {
      border-bottom: 1px solid #e2e2e2;
    }
  </style>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.5.0/css/bootstrap.min.css" integrity="sha256-aAr2Zpq8MZ+YA/D6JtRD3xtrwpEz2IqOS+pWD/7XKIw=" crossorigin="anonymous"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.9/themes/default/style.min.css" integrity="sha256-gX9Z4Eev/EDg9VZ5YIkmKQSqcAHL8tST90dHvtutjTg=" crossorigin="anonymous"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.13.0/css/all.min.css" integrity="sha256-h20CPZ0QyXlBuAw7A+KluUYx/3pK+c7lYEpqLTlxjYQ=" crossorigin="anonymous"/>
  <?php if ( isset( $missingUrls ) || isset ( $backups ) ) { ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-table/1.16.0/bootstrap-table.min.css" integrity="sha256-cCxZ912RWIYqgo3Di4S0U4rdHxVGoqE23gqVU4XNABE=" crossorigin="anonymous"/>
  <?php } ?>
  <?php if ( in_array( $section, ['settings', 'template', 'htaccess'] ) || isset( $metaData['rowid'] ) ) { ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.54.0/codemirror.min.css" integrity="sha256-Ez4TnBJ24JkU8Z622YHGxDaYrslkqNZ62ewvn08Skco=" crossorigin="anonymous"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.54.0/addon/dialog/dialog.min.css" integrity="sha256-pphiyWu5IBaT4px40EH5CFp5eFyvr4Cpyz0ijG+Z+Cg=" crossorigin="anonymous"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.7.1/dropzone.min.css" integrity="sha256-iDg4SF4hvBdxAAFXfdNrl3nbKuyVBU3tug+sFi1nth8=" crossorigin="anonymous"/>
  <?php } ?>
  <?php if ( in_array( $section, ['import', 'install', 'main'] ) ) { ?>
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
  <?php } ?>
</head>
<body>
<?php
if ( !$accessAllowed && $extensionsAllowed ) {
  $section = ''; ?>
  <div class="container">
    <?php showWarning(); ?>
    <div class="modal fade show d-block p-3" id="loginModal" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="false">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 bg-dark">
          <div class="modal-header justify-content-center border-0 py-3">
            <a href=""><img src="<?=dataLogo()?>" height="35" class="d-inline-block align-top border-0" alt=""></a>
          </div>
          <div class="modal-body p-3 bg-secondary rounded-bottom">
            <form method="post" action="" class="needs-validation" novalidate>
              <div class="d-flex">
                <div class="w-100 input-group pr-2">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fas fa-fw fa-eye-slash password-eye"></i></span></div>
                  <input type="password" name="password" class="form-control" placeholder="<?=L( 'Password' )?>" value="" autofocus required>
                </div>
                <div class="ml-auto">
                  <button type="submit" class="btn btn-danger"><i class="fas fa-unlock-alt fa-fw"></i>
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php
}

if ( !$extensionsAllowed ) {
  $section = '';
  addWarning( sprintf( L( 'In order to continue, you must have the following PHP extensions enabled: %s' ), implode( ', ', getMissingExtensions( ['json', 'pdo_sqlite', 'zip'] ) ) ), 4, L( 'System check' ) );
  ?>
  <div class="container">
    <?php showWarning(); ?>
    <div class="modal fade show d-block p-3" id="loginModal" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="false">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 bg-dark">
          <div class="modal-header justify-content-center border-0 py-3">
            <a href=""><img src="<?=dataLogo()?>" height="35" class="d-inline-block align-top border-0" alt=""></a>
          </div>
          <div class="modal-body p-3 bg-light rounded-bottom text-center border border-top-0">
            <a href="" class="btn btn-success"><?=L( 'Check again' )?></a>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php
}

if ( $section == 'install' && !$taskIncomplete ) { ?>
  <div class="container">
    <?php showWarning(); ?>
    <div class="card mb-3 border-0">

      <div class="card-header pt-3 pb-0 bg-dark">
        <div class="row">
          <div class="col mb-3 text-center text-sm-left">
            <a href=""><img src="<?=dataLogo()?>" height="30" class="border-0" alt=""></a>
          </div>
          <div class="col-12 col-sm-auto mb-3 text-center">
            <div class="btn-group btn-group-sm" role="group">
              <a type="button" href="<?=$_SERVER['REQUEST_URI']?>?lang=en" class="btn <?=( $_SESSION['archivarix.lang'] == 'en' ? 'btn-success' : 'btn-light' )?>">English</a>
              <a type="button" href="<?=$_SERVER['REQUEST_URI']?>?lang=ru" class="btn <?=( $_SESSION['archivarix.lang'] == 'ru' ? 'btn-success' : 'btn-light' )?>">Русский</a>
            </div>
          </div>
          <?php if ( !empty( $_SESSION['archivarix.logged'] ) ) { ?>
            <div class="col-12 col-sm-auto mb-3 text-center pl-sm-0 d-block d-sm-flex align-items-center">
              <a class="text-light" href="<?=$_SERVER['REQUEST_URI']?>?logout">
                <i class="fas fa-sign-out-alt fa-fw"></i> <span class="d-sm-none"><?=L( 'Log out' )?></span>
              </a>
            </div>
          <?php } ?>
        </div>
      </div>
      <div class="card-body border border-top-0 rounded-bottom">
        <div class="">
          <form action="" method="post" id="form_import_uuid" class="needs-validation" novalidate>
            <div class="input-group">
              <input type="text" class="form-control" name="uuid" pattern="[0-9a-zA-Z]{16}|[-0-9a-zA-Z]{19}" placeholder="<?=L( 'Enter serial number' )?>" required>
              <div class="input-group-append">
                <button type="submit" class="btn btn-primary rounded-right" <?=!empty( $noProceed ) ? ' disabled' : ''?>><?=L( 'Download' )?></button>
              </div>
              <div class="invalid-feedback"><?=L( 'Serial number has to be in a format of 16 characters XXXXXXXXXXXXXXXX or XXXX-XXXX-XXXX-XXXX' )?></div>
            </div>
            <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
            <input type="hidden" name="action" value="download.serial.install">
          </form>

          <div class="d-flex align-items-center text-secondary small text-uppercase">
            <div class="w-100">
              <hr class="my-0">
            </div>
            <div class="mx-2"><?=L( 'or' )?></div>
            <div class="w-100">
              <hr class="my-0">
            </div>
          </div>

          <form action="" method="post" id="form_import_upload" enctype="multipart/form-data" class="needs-validation" novalidate>
            <div class="input-group">
              <div class="custom-file">
                <input type="file" class="custom-file-input input-upload-file" name="import_file" accept=".zip,application/zip,application/octet-stream,application/x-zip-compressed,multipart/x-zip" id="input_import_file" aria-describedby="button_import_file" required>
                <label class="custom-file-label text-truncate" for="input_import_file"><?=L( 'Choose ZIP file' )?></label>
              </div>
              <div class="input-group-append">
                <button class="btn btn-primary" type="submit" id="button_import_file" <?=!empty( $noProceed ) ? ' disabled' : ''?>><?=L( 'Upload' )?></button>
              </div>
            </div>
            <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
            <input type="hidden" name="action" value="import.upload.install">
          </form>
        </div>

        <?php
        $sourcePath = __DIR__ . DIRECTORY_SEPARATOR . '.content.tmp';
        if ( file_exists( $sourcePath ) ) {
          $imports = getImportsList();
          foreach ( $imports as $import ) { ?>
            <div class="bg-light rounded border shadow p-3 mt-3">
              <div class="row">
                <div class="col-12 col-md-3 mb-3">
                  <img src="<?=$import['screenshot']?>" class="img-fluid w-100 border rounded" onerror="this.src='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAIAAAABLCAIAAAA3cxjrAAAABnRSTlMAAAAAAABupgeRAAAAMklEQVR42u3BAQEAAACCIP+vbkhAAQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAEcGcMsAAQVgyaQAAAAASUVORK5CYII=';">
                </div>
                <div class="col-12 col-md-6 mb-3">
                  <div class="text-uppercase h5"><?=convertIdnToUtf8( $import['info']['settings']['domain'] )?>
                    <a href="<?=$import['url']?>" target="_blank"><i class="fas fa-external-link-alt fa-fw small"></i></a>
                  </div>
                  <div class="small"><?=$import['filename']?>
                    (<?=getHumanSize( $import['filesize'] );?>)
                  </div>
                  <div class="small text-muted">
                    <?=sprintf( L( 'Contains: %d files, %s of data' ), $import['info']['filescount'], getHumanSize( $import['info']['filessize'] ) )?>
                  </div>
                </div>
                <div class="col-12 col-md-3 text-center text-md-right">
                  <button class="btn btn-primary" data-toggle="collapse" data-target="#div_import_stats_<?=$import['id']?>" aria-expanded="false" aria-controls="div_import_stats_<?=$import['id']?>" title="<?=L( 'Stats' )?>">
                    <i class="fas fa-chart-pie fa-fw"></i>
                  </button>
                  <form action="" method="post" class="d-inline" id="form_remove_import_<?=$import['id']?>">
                    <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                    <input type="hidden" name="action" value="import.remove.install">
                    <input type="hidden" name="filename" value="<?=htmlspecialchars( $import['filename'] )?>">
                    <button class="btn btn-danger btn-action" type="button" data-toggle="modal" data-target="#confirm-action" data-source="form_remove_import_<?=$import['id']?>" title="<?=L( 'Delete' )?>">
                      <i class="fas fa-trash-alt fa-fw"></i></button>
                  </form>
                </div>


                <div class="col-12 py-3" id="div_form_import_<?=$import['id']?>">
                  <form action="" method="post" id="form_import_<?=$import['id']?>">
                    <div class="mb-3">
                      <table class="table table-striped table-sm table-responsive">
                        <thead>
                        <tr>
                          <td class="w-100">
                            <div class="form-check font-weight-bold">
                              <input class="form-check-input check-all" data-group="check-<?=$import['id']?>" type="checkbox" checked id="check_all_<?=$import['id']?>"><label class="form-check-label" for="check_all_<?=$import['id']?>"><?=L( 'Hostnames' )?></label>
                            </div>
                          </td>
                          <th><?=L( 'Files' )?></th>
                        </tr>
                        </thead>
                        <?php foreach ( $import['info']['hostnames'] as $hostname ) { ?>
                          <tr>
                            <td>
                              <div class="form-check">
                                <input class="form-check-input check-<?=$import['id']?>" type="checkbox" name="settings[hostnames][]" value="<?=$hostname['hostname']?>" checked id="check_<?=$hostname['hostname']?>_<?=$import['id']?>"><label class="form-check-label" for="check_<?=$hostname['hostname']?>_<?=$import['id']?>"><?=convertIdnToUtf8( $hostname['hostname'] )?></label>
                              </div>
                            </td>
                            <td><?=number_format( $hostname['count'], 0 )?></td>
                          </tr>
                        <?php } ?>
                      </table>
                    </div>

                    <?php if ( !empty( $integrationPrerequisite ) ) { ?>
                      <div class="alert alert-warning">
                        <div class="form-group form-check mb-0">
                          <input type="hidden" name="settings[integration]" value="0">
                          <input class="form-check-input" type="checkbox" name="settings[integration]" value="1" id="check_integration_settings_<?=$import['id']?>" checked>
                          <label class="form-check-label" for="check_integration_settings_<?=$import['id']?>"><?=sprintf( L( 'Integrate with the found installation of %s' ), $integrationPrerequisite )?></label>
                          <small class="form-text"><i class="fas fa-exclamation-triangle fa-fw"></i> <?=sprintf( L( 'Attention! If you do not select the checkbox, then index.php file of %s will be overwritten with Archivarix Loader.' ), $integrationPrerequisite )?>
                          </small>
                        </div>
                      </div>
                    <?php } ?>

                    <?php if ( $import['acms_settings'] ) { ?>
                      <div class="form-group form-check mb-0">
                        <input type="hidden" name="settings[acms_settings]" value="0">
                        <input class="form-check-input" type="checkbox" name="settings[acms_settings]" value="1" id="check_acms_settings_<?=$import['id']?>" checked>
                        <label class="form-check-label" for="check_acms_settings_<?=$import['id']?>"><?=L( 'Import Archivarix CMS settings' )?></label>
                      </div>
                    <?php } ?>

                    <?php if ( $import['loader_settings'] ) { ?>
                      <div class="form-group form-check mb-0">
                        <input type="hidden" name="settings[loader_settings]" value="0">
                        <input class="form-check-input" type="checkbox" name="settings[loader_settings]" value="1" id="check_loader_settings_<?=$import['id']?>" checked>
                        <label class="form-check-label" for="check_loader_settings_<?=$import['id']?>"><?=L( 'Import Archivarix Loader settings' )?></label>
                      </div>
                    <?php } ?>

                    <?php if ( !empty( $import['templates'] ) ) { ?>
                      <div class="form-group form-check mb-0">
                        <input type="hidden" name="settings[templates]" value="0">
                        <input class="form-check-input" type="checkbox" name="settings[templates]" value="1" id="check_templates_<?=$import['id']?>" checked>
                        <label class="form-check-label" for="check_templates_<?=$import['id']?>"><?=sprintf( L( 'Import detected templates (%d)' ), count( $import['templates'] ) )?>
                          (<a href="" data-toggle="collapse" data-target="#div_templates_<?=$import['id']?>" aria-expanded="false" aria-controls="div_templates_<?=$import['id']?>"><?=L( 'show' )?></a>)</label>
                        <div class="collapse list-group mt-2" id="div_templates_<?=$import['id']?>">
                          <li class="list-group-item list-group-item-action list-group-item-secondary h6 m-0 rounded-0"><?=L( 'Templates' )?></li>
                          <?php foreach ( $import['templates'] as $importTemplate ) { ?>
                            <li class="list-group-item list-group-item-action py-1 text-nowrap text-truncate text-monospace rounded-0">
                              <i class="far fa-file-code fa-fw"></i> <?=$importTemplate['name']?>
                            </li>
                          <?php } ?>
                        </div>
                      </div>
                    <?php } ?>

                    <?php if ( !empty( $import['custom_includes'] ) && !inSafeMode() ) { ?>
                    <div class="form-group form-check mb-0">
                      <input type="hidden" name="settings[custom_includes]" value="0">
                      <input class="form-check-input" type="checkbox" name="settings[custom_includes]" value="1" id="check_custom_includes_settings_<?=$import['id']?>">
                      <label class="form-check-label" for="check_custom_includes_settings_<?=$import['id']?>"><?=L( 'Import files from custom \'includes\' directory' )?>
                        (<a href="" data-toggle="collapse" data-target="#div_custom_includes_<?=$import['id']?>" aria-expanded="false" aria-controls="div_custom_includes_<?=$import['id']?>"><?=L( 'show files' )?></a>)</label>
                      <div class="collapse list-group mt-2" id="div_custom_includes_<?=$import['id']?>">
                        <li class="list-group-item list-group-item-action list-group-item-secondary h6 m-0 rounded-0"><?=L( 'Custom Files' )?></li>
                        <?php foreach ( $import['custom_includes'] as $customInclude ) { ?>
                          <li class="list-group-item list-group-item-action px-0 py-1 text-nowrap text-truncate text-monospace rounded-0">
                            <?=str_repeat( '<i class="fas fa-fw"></i>', $customInclude['levels'] )?>
                            <?=( $customInclude['is_dir'] ? '<i class="far fa-fw fa-folder"></i>' : '<i class="fas fa-fw"></i><i class="far fa-fw fa-file-alt"></i>' )?>
                            <?=basename( $customInclude['filename'] )?>
                            <span class="text-muted"><?=( $customInclude['is_dir'] ? '' : '(' . getHumanSize( $customInclude['size'], 0 ) . ')' )?></span>
                          </li>
                        <?php } ?>
                      </div>
                      <div class="small text-danger mt-1">
                        <i class="fas fa-exclamation-triangle fa-fw"></i> <?=L( 'Attention! Any file inside \'includes\' directory can have executable php source code. Do not import files from untrusted sources.' )?>
                      </div>
                      <?php } ?>
                      <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                      <input type="hidden" name="action" value="import.perform.install">
                      <input type="hidden" name="filename" value="<?=htmlspecialchars( $import['filename'] )?>">
                      <input type="hidden" name="disable_history" value="1">
                      <div class="text-center">
                        <button class="btn btn-success mt-3" type="submit">
                          <i class="fas fa-rocket fa-fw"></i> <?=L( 'Run import' )?></button>
                      </div>
                  </form>
                </div>
              </div>

              <div class="py-3 collapse stats-collapse" id="div_import_stats_<?=$import['id']?>" data-id="<?=$import['info']['id']?>">
                <?php printStats( $import['info'] ) ?>
              </div>

            </div>
            <?php
          }
        }
        ?>
      </div>


    </div>
  </div>
  <?php
}


if ( $section == 'install' && $taskIncomplete ) { ?>
  <div class="pt-3 container">
    <div class="p-4 rounded-top bg-dark text-light shadow">
      <div class="d-flex align-items-center">
        <div class=""><i class="fas fa-info-circle fa-3x mr-3"></i></div>
        <div class="h4"><?=L( 'Attention! Do not close the browser window and do not stop loading, the page will reload itself.' )?></div>
      </div>
      <form action="" method="post" id="form_task_part">
        <?=printFormFields( $taskIncompleteParams )?>
      </form>
      <div class="small text-right">
        <?=L( 'Previous execution' )?>: <?=round( ( microtime( true ) - ACMS_START_TIME ), 3 )?>s
      </div>
    </div>
    <?php
    $stats = unserialize( $taskStats );
    if ( !empty( $stats['processed'] ) && !empty( $stats['total'] ) ) {
      ?>
      <div class="progress rounded-0">
        <div role="progressbar" aria-valuenow="<?=ceil( ( $stats['processed'] / $stats['total'] ) * 100 )?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=round( ( $stats['processed'] / $stats['total'] ) * 100 )?>%" class="progress-bar progress-bar-striped progress-bar-animated bg-success rounded-0"></div>
      </div>
    <?php } ?>
  </div>
<?php }

if ( $section != 'install' && $accessAllowed && $extensionsAllowed ) { ?>
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top" style="">
    <a class="navbar-brand" href=""><img src="<?=dataLogo()?>" height="30" class="d-inline-block align-top" alt="Logo Archivarix"></a>
    <button class="navbar-toggler border-0" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse justify-content-end" id="navbarSupportedContent">
      <ul class="navbar-nav">
        <li class="nav-item">
          <form action="" method="post" id="formSearchNew">
            <input type="hidden" name="search" value=""/>
            <input type="hidden" name="replace" value=""/>
            <input type="hidden" name="regex" value=""/>
            <input type="hidden" name="text_files_search" value=""/>
            <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
            <input type="hidden" name="action" value="searchreplace.code"/>
            <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
            <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
            <input type="hidden" name="type" value="new"/>
            <a class="nav-link" type="button" id="clickSearchNew"><i class="fas fa-search fa-fw"></i> <?=L( 'Search & Replace' )?>
            </a>
          </form>
        </li>
        <li class="nav-item">
          <form action="" method="post" id="formToolsView">
            <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
            <input type="hidden" name="action" value="tools.view"/>
            <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
            <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
            <a class="nav-link" type="button" id="clickToolsView"><i class="fas fa-tools fa-fw"></i> <?=L( 'Tools' )?>
            </a>
          </form>
        </li>
        <li class="nav-item">
          <form action="" method="post" id="formHistoryNew">
            <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
            <input type="hidden" name="action" value="history.edit"/>
            <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
            <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
            <a class="nav-link" type="button" id="clickHistory"><i class="fas fa-history fa-fw"></i> <?=L( 'History' )?>
            </a>
          </form>
        </li>
        <li class="nav-item">
          <form action="" method="post" id="formSettingsView">
            <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
            <input type="hidden" name="action" value="settings.view"/>
            <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
            <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
            <a class="nav-link" type="button" id="clickSettings"><i class="fas fa-cog fa-fw"></i>
              <?=L( 'Settings' )?>
            </a>
          </form>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?=$_SERVER['REQUEST_URI']?>"><i class="fas fa-info-circle fa-fw"></i>
            <span class="d-lg-none"><?=L( 'Information' )?></span></a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="dropdown01" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fas fa-language fa-fw"></i>
            <span class="d-lg-none"><?=L( 'Language' )?></span></a>
          <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdownMenuLink">
            <a class="dropdown-item" href="<?=$_SERVER['REQUEST_URI']?>?lang=en">English</a>
            <a class="dropdown-item" href="<?=$_SERVER['REQUEST_URI']?>?lang=ru">Русский</a>
          </div>
        </li>
        <?php if ( !empty( $_SESSION['archivarix.logged'] ) ) { ?>
          <li class="nav-item">
            <a class="nav-link" href="<?=$_SERVER['REQUEST_URI']?>?logout"><i class="fas fa-sign-out-alt fa-fw"></i>
              <span class="d-lg-none"><?=L( 'Log out' )?></span></a>
          </li>
        <?php } ?>
      </ul>
    </div>
  </nav>

  <?php if ( $taskIncomplete ) { ?>
    <div class="pt-4 container">
      <div class="px-4 py-3 rounded-top bg-dark text-light shadow">
        <div class="d-flex align-items-center">
          <div class=""><i class="fas fa-info-circle fa-3x mr-3"></i></div>
          <div class="h4"><?=L( 'Attention! Do not close the browser window and do not stop loading, the page will reload itself.' )?></div>
        </div>
        <form action="" method="post" id="form_task_part">
          <?=printFormFields( $taskIncompleteParams )?>
        </form>
        <div class="small text-right">
          <?=L( 'Previous execution' )?>: <?=round( ( microtime( true ) - ACMS_START_TIME ), 3 )?>s
        </div>
      </div>
      <?php
      $stats = unserialize( $taskStats );
      if ( !empty( $stats['processed'] ) && !empty( $stats['total'] ) ) {
        ?>
        <div class="progress rounded-0">
          <div role="progressbar" aria-valuenow="<?=ceil( ( $stats['processed'] / $stats['total'] ) * 100 )?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=round( ( $stats['processed'] / $stats['total'] ) * 100 )?>%" class="progress-bar progress-bar-striped progress-bar-animated bg-success rounded-0"></div>
        </div>
      <?php } ?>
    </div>
  <?php } ?>

  <?php if ( empty( $taskIncomplete ) ) { ?>
    <div class="pt-3 container-fluid">
      <div class="row h-100">
        <div class="col-12 col-lg-3 border-0 mb-2 order-2 order-md-1" id="sidebar">
          <!-- SEARCH -->
          <div class="bg-white sticky-top px-1">
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text bg-white px-2"><i class="fas fa-filter fa-fw"></i></span>
              </div>
              <input type="search" value="<?=htmlspecialchars( $filterValue )?>" class="form-control border-left-0 pl-1" id="treeSearch" placeholder="<?=L( 'Filter' )?>"/>
            </div>
            <hr class="mt-1 mb-0 d-none d-lg-block">
          </div>

          <div class="p-1">
            <?php if ( inSafeMode() ) { ?>
              <div class="alert-info rounded p-2 mb-1 small text-center text-uppercase">
                <i class="fas fa-shield-alt fa-fw"></i> <?=L( 'Safe mode is On' )?></div>
            <?php } ?>

            <?php if ( empty( $ACMS['ACMS_ALLOWED_IPS'] ) && !strlen( ACMS_PASSWORD ) && !strlen( $ACMS['ACMS_PASSWORD'] ) && !strlen( ACMS_SAFE_PASSWORD ) && !strlen( $ACMS['ACMS_SAFE_PASSWORD'] ) ) { ?>
              <div class="bg-danger text-light rounded p-2 mb-1 small">
                <div class="">
                  <i class="fas fa-exclamation-triangle pr-2"></i><?=L( 'Warning! IP restriction or password is not configured. Anybody can access this page.' )?>
                </div>
                <hr class="bg-light my-2">
                <div class="text-center">
                  <form action="" method="post">
                    <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                    <input type="hidden" name="action" value="settings.view"/>
                    <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                    <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
                    <button type="submit" class="btn btn-light btn-sm">
                      <i class="fas fa-lock fa-fw"></i> <?=L( 'Set password' )?>
                    </button>
                  </form>
                  </a>
                </div>
              </div>
            <?php } ?>

            <?php if ( !empty( $_SESSION['archivarix.expert'] ) && count( $_POST ) ) { ?>
              <!-- DEBUG MODE -->
              <div class="p-3 text-white bg-dark rounded-top text-monospace text-uppercase d-flex">
                <div><?=L( 'Debug' )?></div>
                <div class="ml-auto"><i class="fas fa-memory"></i> <?=getHumanSize( memory_get_peak_usage() );?></div>
              </div>
              <div class="bg-info text-white text-monospace p-3 mb-1 rounded-bottom">
                <pre class="text-white small"><?php printArrayHuman( $_POST ); ?><?php if ( !empty( $_FILES ) ) printArrayHuman( $_FILES ); ?></pre>
              </div>
            <?php } ?>

            <?php if ( empty( $domains ) ) { ?>
              <div class="text-center mb-2">
                <button class="btn btn-success btn-block" type="button" id="createNewUrl_<?=$uuidSettings['domain']?>" data-toggle="modal" data-target="#createNewUrl" data-hostname="<?=$uuidSettings['domain']?>">
                  <i class="fas fa-file fa-fw"></i> <?=L( 'Create new URL' )?>
                </button>
              </div>
              <div class="text-center mb-2">
                <form action="" method="post">
                  <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                  <input type="hidden" name="action" value="import.view">
                  <button class="btn btn-primary btn-block" type="submit">
                    <i class="fas fa-file-import fa-fw"></i> <?=L( 'Websites import' )?></button>
                </form>
              </div>
            <?php } ?>

            <!-- TEMPLATES -->
            <?php $listTemplates = getTemplates();
            if ( $listTemplates ) { ?>
              <div id="accordion_templates">
                <div class="card border-0 bg-light mb-1">
                  <div class="card-header border-0 p-0 rounded h5 px-2 py-3">
                    <i class="fas fa-file-code fa-fw"></i> <?=L( 'Templates' )?>
                  </div>
                </div>
              </div>
              <div class="collapse multi-collapse show urls-collapse" id="collapse_templates">
                <div class="card-body p-0">
                  <div class="mb-2 small list-group">
                    <?php foreach ( $listTemplates as $currTemplate ) { ?>
                      <div class="d-flex">
                        <a href="#" class="template-link list-group-item list-group-item-action border-0 p-0 m-0 rounded-0 <?=( ( isset( $template ) && $template['name'] == $currTemplate['name'] ) ? 'bg-success text-white' : '' )?>" data-name="<?=$currTemplate['name']?>"><i class="fas fa-fw"></i><i class="fas fa-file-code fa-fw"></i><?=$currTemplate['name']?>
                        </a>
                        <a href="#" class="template-create-page-link px-2 <?=( ( isset( $template ) && $template['name'] == $currTemplate['name'] ) ? 'btn-success text-white' : 'text-success' )?>" data-name="<?=$currTemplate['name']?>"><i class="far fa-plus-square fa-fw"></i></a>
                      </div>
                    <?php } ?>
                  </div>
                </div>
              </div>
              <hr class="mx-0 my-1">
            <?php } ?>

            <?php foreach ( $domains as $domainName => $domainData ) { ?>
              <!-- DOMAINS TREE -->
              <div id="accordion_<?=$domainData['safeName']?>">
                <div class="card border-0 rounded-top bg-light mb-1">
                  <div class="card-header border-0 p-0 rounded">
                    <div class="d-flex align-items-stretch">
                      <div class="h5 pl-2 pr-1 py-3 mb-0 rounded-left d-flex align-items-center">
                        <a class="text-dark" href="//<?=convertDomain( $domainName )?>" target="_blank"><i class="fas fa-external-link-alt fa-fw small"></i></a>
                      </div>
                      <div class="h5 px-1 py-3 mb-0">
                      <span class="text-break domain-name-toggle" data-toggle="collapse" data-target="#collapse_<?=$domainData['safeName']?>" aria-expanded="true" aria-controls="collapse_<?=$domainData['safeName']?>" role="button">
                      <?=convertIdnToUtf8( $domainName )?></span>
                      </div>
                      <div class="ml-auto text-nowrap align-items-center pr-2 d-flex align-items-center">
                        <button class="btn btn-sm btn-primary mr-1 urls-tree-expand" type="button" data-jstree="jstree_<?=$domainData['safeName']?>" title="<?=L( 'Expand/Collapse all' )?>">
                          <i class="fas fa-expand-alt fa-fw fa-rotate-90"></i>
                        </button>
                        <button class="btn btn-sm btn-primary" type="button" id="createNewUrl_<?=$domainData['safeName']?>" data-toggle="modal" data-target="#createNewUrl" data-hostname="<?=$domainName?>" title="<?=L( 'Create new URL' )?>">
                          <i class="fas fa-file fa-fw"></i>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="collapse multi-collapse show urls-collapse" id="collapse_<?=$domainData['safeName']?>">
                <div class="card-body p-0">
                  <?php if ( $urlsTotal[$domainName] > $ACMS['ACMS_URLS_LIMIT'] ) {
                    $pageCurrent = 1;
                    $pagesTotal  = $ACMS['ACMS_URLS_LIMIT'] ? ceil( $urlsTotal[$domainName] / $ACMS['ACMS_URLS_LIMIT'] ) : 0;
                    if ( key_exists( $domainName, $urlOffsets ) ) {
                      $pageCurrent = $urlOffsets[$domainName];
                    }
                    ?>
                    <div class="alert alert-warning small mb-2"><?=L( 'Pagination is on. You may increase the limit in Settings at the risk of running out of RAM. The current limit per page is ' ) . $ACMS['ACMS_URLS_LIMIT'] . '.'?></div>
                    <nav>
                      <ul class="pagination pagination-sm justify-content-center mb-2 flex-wrap">
                        <?php
                        if ( $pagesTotal ) for ( $i = 1; $i <= $pagesTotal; $i++ ) {
                          if ( $pageCurrent > 1 && $i == 1 ) { ?>
                            <li class="page-item">
                              <a class="page-link changePage" type="button" data-page="<?=( $pageCurrent - 1 )?>" data-domain="<?=htmlspecialchars( $domainName )?>"><?=L( 'Previous' )?></a>
                            </li>
                          <?php }
                          if ( $i > 2 && $i < $pageCurrent ) { ?>
                            <li class="page-item disabled">
                              <a class="page-link">&hellip;</a>
                            </li>
                            <?php while ( $i < $pageCurrent ) $i++ ?>
                          <?php }
                          if ( $i > 2 && $i > $pageCurrent + 4 ) { ?>
                            <li class="page-item disabled">
                              <a class="page-link">&hellip;</a>
                            </li>
                            <?php while ( $i < $pagesTotal ) $i++ ?>
                          <?php }
                          if ( $i != $pageCurrent && ( $i > $pageCurrent - 5 || $i < $pageCurrent + 5 ) ) { ?>
                            <li class="page-item">
                              <a class="page-link changePage" type="button" data-page="<?=$i?>" data-domain="<?=htmlspecialchars( $domainName )?>"><?=$i?></a>
                            </li>
                          <?php }
                          if ( $i == $pageCurrent ) { ?>
                            <li class="page-item active" aria-current="page">
                              <span class="page-link"><?=$i?></span>
                            </li>
                          <?php }
                        }
                        ?>
                      </ul>
                    </nav>
                  <?php } ?>
                  <div id="jstree_<?=$domainData['safeName']?>" data-domain-converted="<?=convertdomain( $domainName )?>" class="mb-2">
                    <?php printArrayList( $domainData['tree'], $domainData['pathUrls'] ); ?>
                  </div>
                </div>
              </div>
            <?php } ?>
            <hr class="d-lg-none">
          </div>
        </div>


        <div class="col-12 col-lg-9 ml-auto pt-0 order-1 order-md-2 pb-3" id="main">
          <?php
          showWarning();
          if ( $section == 'settings' ) {
            $customFilesOnly = getOnlyCustomFiles( getCustomFiles() );
            if ( empty( $subSection ) ) $subSection = 'acms';
            ?>
            <div class="card mb-3">
              <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" role="tablist" style="flex-wrap: inherit;">
                  <li class="nav-item">
                    <a class="nav-link <?=( $subSection == 'acms' ) ? 'active' : ''?>" id="settings_cms_tab" data-toggle="tab" href="#settings_cms" role="tab" aria-controls="settings_cms" aria-selected="<?=( $subSection == 'acms' ) ? 'true' : 'false'?>"><?=L( 'Archivarix CMS' )?></a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link <?=( $subSection == 'loader' ) ? 'active' : ''?>" id="settings_loader_tab" data-toggle="tab" href="#settings_loader" role="tab" aria-controls="settings_loader" aria-selected="<?=( $subSection == 'loader' ) ? 'true' : 'false'?>"><?=L( 'Archivarix Loader' )?></a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link <?=( $subSection == 'custom' ) ? 'active' : ''?>" id="settings_includes_tab" data-toggle="tab" href="#settings_includes" role="tab" aria-controls="settings_includes" aria-selected="<?=( $subSection == 'custom' ) ? 'true' : 'false'?>"><?=L( 'Custom Files' )?></a>
                  </li>
                </ul>
              </div>


              <div class="card-body">
                <div class="tab-content">
                  <div class="tab-pane fade <?=( $subSection == 'acms' ) ? 'show active' : ''?>" id="settings_cms" role="tabpanel" aria-labelledby="settings_cms_tab">
                    <form action="" method="post" id="form_acms_settings" class="needs-validation" novalidate>
                      <?php if ( !inSafeMode() ) { ?>
                        <div class="form-group">
                          <label class="mb-1" for="acms_settings_password"><?=L( 'Password' )?></label>
                          <?php if ( !empty( $ACMS['ACMS_PASSWORD'] ) ) { ?>
                            <?php if ( !empty( ACMS_PASSWORD ) ) { ?>
                              <div class="alert-danger rounded p-2 mb-1 small"><?=L( 'Current password is hardcoded in the source-code. Password settings below will not affect hardcoded password.' )?></div>
                            <?php } ?>
                            <div class="custom-control custom-checkbox mb-1">
                              <input type="checkbox" class="custom-control-input" name="remove_password" value="1" id="acms_settings_remove_password">
                              <label class="custom-control-label" for="acms_settings_remove_password"><?=L( 'Remove current password' )?></label>
                            </div>
                          <?php } ?>
                          <div class="input-group">
                            <div class="input-group-prepend">
                              <span class="input-group-text"><i class="fas fa-fw fa-eye-slash password-eye"></i></span>
                            </div>
                            <input class="form-control" type="password" name="settings[ACMS_PASSWORD]" placeholder="" pattern=".{1,}" id="acms_settings_password">
                          </div>
                          <small class="form-text text-muted"><?=L( 'Enter a password to set or leave empty to keep an existing password.' )?></small>
                        </div>
                        <div class="form-group">
                          <label class="mb-1" for="acms_settings_password"><?=L( 'Safe mode password' )?></label>
                          <?php if ( !empty( $ACMS['ACMS_SAFE_PASSWORD'] ) ) { ?>
                            <?php if ( !empty( ACMS_SAFE_PASSWORD ) ) { ?>
                              <div class="alert-danger rounded p-2 mb-1 small"><?=L( 'Current safe mode password is also hardcoded in the source-code. Password settings below will not affect hardcoded password.' )?></div>
                            <?php } ?>
                            <div class="custom-control custom-checkbox mb-1">
                              <input type="checkbox" class="custom-control-input" name="remove_safe_password" value="1" id="acms_settings_remove_safe_password">
                              <label class="custom-control-label" for="acms_settings_remove_safe_password"><?=L( 'Remove current safe mode password' )?></label>
                            </div>
                          <?php } ?>
                          <div class="input-group">
                            <div class="input-group-prepend">
                              <span class="input-group-text"><i class="fas fa-fw fa-eye-slash password-eye"></i></span>
                            </div>
                            <input class="form-control" type="password" name="settings[ACMS_SAFE_PASSWORD]" placeholder="" pattern=".{1,}" id="acms_settings_safe_password">
                          </div>
                          <small class="form-text text-muted"><?=L( 'Enter a password to set or leave empty to keep an existing password.' )?> <?=L( 'In a safe mode you cannot create/edit custom files with a php code or do anything to the server.' )?></small>
                        </div>
                      <?php } ?>
                      <div class="form-group">
                        <label for="acms_settings_urls"><?=L( 'Limit URLs menu' )?></label>
                        <input class="form-control" type="number" name="settings[ACMS_URLS_LIMIT]" placeholder="" pattern="[\d]{1,}" value="<?=htmlspecialchars( $ACMS['ACMS_URLS_LIMIT'] )?>" id="acms_settings_urls" required>
                        <small class="form-text text-muted"><?=L( 'URLs menu will have pagination for domains/subdomains with higher number of URLs.' )?></small>
                      </div>
                      <div class="form-group">
                        <label for="acms_settings_matches"><?=L( 'Results in Search & Replace' )?></label>
                        <input class="form-control" type="number" name="settings[ACMS_MATCHES_LIMIT]" placeholder="" pattern="[\d]{1,}" value="<?=htmlspecialchars( $ACMS['ACMS_MATCHES_LIMIT'] )?>" id="acms_settings_matches" required>
                        <small class="form-text text-muted"><?=L( 'Limit the number of results in Search & Replace. It does not affect replacing process.' )?></small>
                      </div>
                      <div class="form-group">
                        <label for="acms_settings_timeout"><?=L( 'Timeout in seconds' )?></label>
                        <input class="form-control" type="number" name="settings[ACMS_TIMEOUT]" placeholder="" pattern="[\d]{1,}" min="5" value="<?=htmlspecialchars( $ACMS['ACMS_TIMEOUT'] )?>" id="acms_settings_timeout" required>
                        <small class="form-text text-muted"><?=L( 'Recommended time is 30 seconds.' )?></small>
                      </div>
                      <div class="form-group form-check">
                        <input type="hidden" name="settings[ACMS_DISABLE_HISTORY]" value="0">
                        <input class="form-check-input" type="checkbox" name="settings[ACMS_DISABLE_HISTORY]" value="1" id="acms_settings_history" <?=$ACMS['ACMS_DISABLE_HISTORY'] ? 'checked' : ''?>>
                        <label class="form-check-label" for="acms_settings_history"><?=L( 'Disable history' )?></label>
                        <small class="form-text text-muted"><?=L( 'This will also clear all existing history.' )?></small>
                      </div>
                      <div class="form-group">
                        <label for="acms_settings_domain"><?=L( 'Custom domain' )?></label>
                        <input class="form-control" type="text" name="settings[ACMS_CUSTOM_DOMAIN]" pattern="[-a-z\d.]*" placeholder="" value="<?=htmlspecialchars( $ACMS['ACMS_CUSTOM_DOMAIN'] )?>" id="acms_settings_domain">
                        <small class="form-text text-muted"><i class="fas fa-exclamation-triangle fa-fw"></i> <?=L( 'Leave empty in most cases.' )?> <?=L( 'Set only if switch between subdomains is not working correctly.' )?>
                        </small>
                      </div>
                      <?php if ( !inSafeMode() ) { ?>
                        <div class="form-group">
                          <label for="acms_settings_ips"><?=L( 'Restrict by IP' )?></label>
                          <input class="form-control" type="text" , name="settings[ACMS_ALLOWED_IPS]" pattern="[\d./, :]*" placeholder="" value="<?=htmlspecialchars( str_replace( ',', ', ', $ACMS['ACMS_ALLOWED_IPS'] ) )?>" id="acms_settings_ips">
                          <small class="form-text text-muted"><i class="fas fa-exclamation-triangle fa-fw"></i> <?=L( 'Be careful as your IP may change and you will restrict yourself out. Enter IP addresses or CIDR separated by commas.' )?>
                          </small>
                        </div>
                      <?php } ?>
                      <div class="text-right">
                        <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                        <input type="hidden" name="action" value="set.acms.settings">
                        <button type="submit" class="btn btn-primary"><?=L( 'Save' )?></button>
                      </div>
                    </form>

                    <?php if ( !inSafeMode() ) { ?>
                      <div class="d-flex align-items-center text-secondary small text-uppercase my-3">
                        <div class="w-100">
                          <hr class="my-0">
                        </div>
                        <div class="mx-2 text-nowrap"><?=L( 'or' )?></div>
                        <div class="w-100">
                          <hr class="my-0">
                        </div>
                      </div>

                      <div class="row">
                        <div class="col-12 col-sm-auto mb-3">
                          <form class="d-inline" action="" method="post">
                            <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                            <input type="hidden" name="action" value="download.acms.json">
                            <button class="btn btn-primary btn-block" type="submit">
                              <i class="fas fa-file-download fw-fw"></i> <?=L( 'Download' )?></button>
                          </form>
                        </div>
                        <div class="col mb-3">
                          <form action="" method="post" id="form_import_acms_json" enctype="multipart/form-data" class="d-inline needs-validation" novalidate>
                            <div class="input-group">
                              <div class="custom-file">
                                <input type="file" class="custom-file-input input-upload-file" name="file" accept=".json,application/json" id="input_acms_file" aria-describedby="button_import_file" required>
                                <label class="custom-file-label text-truncate" for="input_acms_file"><?=L( 'Choose JSON file with settings' )?></label>
                              </div>
                              <div class="input-group-append">
                                <button class="btn btn-primary" type="submit" id="button_import_file">
                                  <i class="fas fa-file-upload fw-fw"></i> <?=L( 'Upload' )?></button>
                              </div>
                            </div>
                            <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                            <input type="hidden" name="action" value="upload.acms.json">
                          </form>
                        </div>
                      </div>
                    <?php } ?>

                  </div>

                  <div class="tab-pane fade pb-0  <?=( $subSection == 'loader' ) ? 'show active' : ''?>" id="settings_loader" role="tabpanel" aria-labelledby="settings_loader_tab">

                    <form action="" method="post" id="form_loader_settings" class="needs-validation" novalidate>
                      <div class="form-group">
                        <label for="loader_settings_mode"><?=L( 'Loader mode' )?></label>
                        <select class="form-control" name="settings[ARCHIVARIX_LOADER_MODE]" id="loader_settings_mode" required>
                          <?php
                          $loaderModes = [
                            ['value' => 0, 'label' => L( 'This website only (default)' )],
                            ['value' => -1, 'label' => L( 'This website only, 404 for missing URLs' )],
                            ['value' => 1, 'label' => L( 'Integration with a 3th party CMS, main page this website' )],
                            ['value' => 2, 'label' => L( 'Integration with a 3th party CMS, main page other system' )],
                          ];
                          foreach ( $loaderModes as $loaderMode ) { ?>
                            <option value="<?=$loaderMode['value']?>"<?=( $LOADER['ARCHIVARIX_LOADER_MODE'] == $loaderMode['value'] ) ? ' selected' : ''?>><?=$loaderMode['label']?></option>
                          <?php }
                          ?>
                        </select>
                        <small class="form-text text-muted"><?=L( 'Switch mode if you need to make an integration with 3rd party system (i.e. Wordpress).' )?></small>
                      </div>
                      <div class="form-group">
                        <label for="loader_settings_protocol"><?=L( 'Protocol' )?></label>
                        <select class="form-control" name="settings[ARCHIVARIX_PROTOCOL]" id="loader_settings_protocol" required>
                          <?php
                          $loaderProtocols = [
                            ['value' => 'any', 'label' => L( 'HTTP and HTTPS (default)' )],
                            ['value' => 'https', 'label' => L( 'HTTPS' )],
                            ['value' => 'http', 'label' => L( 'HTTP' )],
                          ];
                          foreach ( $loaderProtocols as $loaderProtocol ) { ?>
                            <option value="<?=$loaderProtocol['value']?>"<?=( $LOADER['ARCHIVARIX_PROTOCOL'] == $loaderProtocol['value'] ) ? ' selected' : ''?>><?=$loaderProtocol['label']?></option>
                          <?php }
                          ?>
                        </select>
                        <small class="form-text text-muted"><?=L( 'Select protocols the website should work on.' )?></small>
                      </div>
                      <div class="form-group">
                        <label for="loader_settings_protocol"><?=L( 'Rules for insert/replace of custom files and scripts' )?></label>
                        <div class="loader-settings-rules-wrapper" id="sortwrapper">
                          <?php foreach ( $LOADER['ARCHIVARIX_INCLUDE_CUSTOM'] as $customRule ) { ?>
                            <div class="loader-custom-rule-block border rounded-bottom bg-light p-0 small mb-3 position-relative ">
                              <div class="position-absolute" style="top:-10px; left:-10px;">
                                <span class="fa-stack sort-handle bg-transparent" type="button" title="<?=L( 'Drag to sort' )?>">
                                  <i class="fas fa-circle fa-stack-2x fa-fw text-dark"></i>
                                  <i class="fas fa-sort fa-stack-1x fa-fw fa-inverse"></i>
                                </span>
                              </div>
                              <div class="position-absolute" style="top:-10px; right:-10px;">
                                <span class="fa-stack remove-loader-custom-rule bg-transparent" type="button">
                                  <i class="fas fa-circle fa-stack-2x fa-fw text-danger"></i>
                                  <i class="fas fa-trash-alt fa-stack-1x fa-fw fa-inverse"></i>
                                </span>
                              </div>
                              <hr class="bg-success p-1 m-0">
                              <div class="p-3">
                                <div class="form-group">
                                  <label class="mb-1"><?=L( 'Filename' )?></label>
                                  <div class="input-group">
                                    <input type="text" class="form-control form-control-sm" name="settings[ARCHIVARIX_INCLUDE_CUSTOM][FILE][]" value="<?=htmlspecialchars( $customRule['FILE'] )?>">
                                    <div class="input-group-append">
                                      <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><?=L( 'or select' )?></button>
                                      <div class="dropdown-menu dropdown-menu-right">
                                        <?php foreach ( $customFilesOnly as $customFile ) { ?>
                                          <a class="dropdown-item put-custom-file" type="button" data-filename="<?=htmlspecialchars( $customFile['filename'] )?>">
                                            <i class="far <?=$customFile['mime']['icon']?> fa-fw"></i>
                                            <?=htmlspecialchars( $customFile['filename'] )?>
                                          </a>
                                        <?php } ?>
                                        <?php if ( !count( $customFilesOnly ) ) { ?>
                                          <a class="dropdown-item"><?=L( 'Empty' )?></a>
                                        <?php } ?>
                                      </div>
                                    </div>
                                  </div>
                                  <small class="form-text text-muted"><?=L( 'Files has to be placed into .content.xxxxxx/includes/ directory.' )?></small>
                                </div>
                                <div class="form-group">
                                  <label class="mb-1"><?=L( 'Search for a keyphrase' )?></label>
                                  <div class="input-group">
                                    <input class="form-control form-control-sm text-monospace input-custom-keyphrase" name="settings[ARCHIVARIX_INCLUDE_CUSTOM][KEYPHRASE][]" value="<?=htmlspecialchars( $customRule['KEYPHRASE'] )?>">
                                    <div class="input-group-append">
                                      <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><?=L( 'or select' )?></button>
                                      <div class="dropdown-menu dropdown-menu-right">
                                        <a class="dropdown-item put-custom-rule" type="button" data-keyphrase="&lt;/head&gt;" data-regex="0" data-limit="1" data-position="-1"><?=L( 'Before' )?>
                                          <span class="text-monospace">&lt;/head&gt;</span></a>
                                        <a class="dropdown-item put-custom-rule" type="button" data-keyphrase="&lt;/body&gt;" data-regex="0" data-limit="1" data-position="-1"><?=L( 'Before' )?>
                                          <span class="text-monospace">&lt;/body&gt;</span></a>
                                        <a class="dropdown-item put-custom-rule" type="button" data-keyphrase="&lt;body[^&gt;]*&gt;" data-regex="1" data-limit="1" data-position="1"><?=L( 'After' )?>
                                          <span class="text-monospace">&lt;body&gt;</span></a>
                                      </div>
                                    </div>
                                  </div>
                                </div>
                                <div class="form-group">
                                  <label class="mb-1"><?=L( 'Regular expression' )?></label>
                                  <select class="form-control form-control-sm select-custom-regex" name="settings[ARCHIVARIX_INCLUDE_CUSTOM][REGEX][]">
                                    <option value="0" <?=$customRule['REGEX'] == 0 ? 'selected' : ''?>><?=L( 'No' )?></option>
                                    <option value="1" <?=$customRule['REGEX'] == 1 ? 'selected' : ''?>><?=L( 'Yes' )?></option>
                                  </select>
                                </div>
                                <div class="form-group">
                                  <label class="mb-1"><?=L( 'Maximum inserts/replacements' )?></label>
                                  <input type="text" class="form-control form-control-sm input-custom-limit" value="<?=htmlspecialchars( $customRule['LIMIT'] )?>" name="settings[ARCHIVARIX_INCLUDE_CUSTOM][LIMIT][]">
                                </div>
                                <div class="form-group">
                                  <label class="mb-1"><?=L( 'Insert' )?></label>
                                  <select class="form-control form-control-sm select-custom-position" name="settings[ARCHIVARIX_INCLUDE_CUSTOM][POSITION][]">
                                    <option value="-1" <?=$customRule['POSITION'] == -1 ? 'selected' : ''?>><?=L( 'Before the keyphrase' )?></option>
                                    <option value="0" <?=$customRule['POSITION'] == 0 ? 'selected' : ''?>><?=L( 'Replace the keyphrase' )?></option>
                                    <option value="1" <?=$customRule['POSITION'] == 1 ? 'selected' : ''?>><?=L( 'After the keyphrase' )?></option>
                                  </select>
                                </div>
                              </div>
                            </div>
                          <?php } ?>

                          <div class="d-none loader-custom-rule-block border rounded-bottom bg-light small mb-3 position-relative">
                            <div class="position-absolute" style="top:-10px; left:-10px;">
                                <span class="fa-stack sort-handle bg-transparent" type="button" title="<?=L( 'Drag to sort' )?>">
                                  <i class="fas fa-circle fa-stack-2x fa-fw text-dark"></i>
                                  <i class="fas fa-sort fa-stack-1x fa-fw fa-inverse"></i>
                                </span>
                            </div>
                            <div class="position-absolute" style="top:-10px; right:-10px;">
                                <span class="fa-stack remove-loader-custom-rule bg-transparent" type="button">
                                  <i class="fas fa-circle fa-stack-2x text-danger"></i>
                                  <i class="fas fa-trash-alt fa-stack-1x fa-inverse"></i>
                                </span>
                            </div>
                            <hr class="bg-secondary p-1 m-0">
                            <div class="p-3">
                              <div class="form-group">
                                <label class="mb-1"><?=L( 'Filename' )?></label>
                                <div class="input-group">
                                  <input type="text" class="form-control form-control-sm" name="settings[ARCHIVARIX_INCLUDE_CUSTOM][FILE][]">
                                  <div class="input-group-append">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><?=L( 'or select' )?></button>
                                    <div class="dropdown-menu dropdown-menu-right">
                                      <?php foreach ( $customFilesOnly as $customFile ) { ?>
                                        <a class="dropdown-item put-custom-file" type="button" data-filename="<?=htmlspecialchars( $customFile['filename'] )?>">
                                          <i class="far <?=$customFile['mime']['icon']?> fa-fw"></i>
                                          <?=htmlspecialchars( $customFile['filename'] )?>
                                        </a>
                                      <?php } ?>
                                      <?php if ( !count( $customFilesOnly ) ) { ?>
                                        <a class="dropdown-item"><?=L( 'Empty' )?></a>
                                      <?php } ?>
                                    </div>
                                  </div>
                                </div>
                                <small class="form-text text-muted"><?=L( 'Files has to be placed into .content.xxxxxx/includes/ directory.' )?></small>
                              </div>
                              <div class="form-group">
                                <label class="mb-1"><?=L( 'Search for a keyphrase' )?></label>
                                <div class="input-group">
                                  <input class="form-control form-control-sm text-monospace input-custom-keyphrase" name="settings[ARCHIVARIX_INCLUDE_CUSTOM][KEYPHRASE][]">
                                  <div class="input-group-append">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><?=L( 'or select' )?></button>
                                    <div class="dropdown-menu dropdown-menu-right">
                                      <a class="dropdown-item put-custom-rule" type="button" data-keyphrase="&lt;/head&gt;" data-regex="0" data-limit="1" data-position="-1"><?=L( 'Before' )?>
                                        <span class="text-monospace">&lt;/head&gt;</span></a>
                                      <a class="dropdown-item put-custom-rule" type="button" data-keyphrase="&lt;/body&gt;" data-regex="0" data-limit="1" data-position="-1"><?=L( 'Before' )?>
                                        <span class="text-monospace">&lt;/body&gt;</span></a>
                                      <a class="dropdown-item put-custom-rule" type="button" data-keyphrase="&lt;body[^&gt;]*&gt;" data-regex="1" data-limit="1" data-position="1"><?=L( 'After' )?>
                                        <span class="text-monospace">&lt;body&gt;</span></a>
                                    </div>
                                  </div>
                                </div>
                              </div>
                              <div class="form-group">
                                <label class="mb-1"><?=L( 'Regular expression' )?></label>
                                <select class="form-control form-control-sm select-custom-regex" name="settings[ARCHIVARIX_INCLUDE_CUSTOM][REGEX][]">
                                  <option value="0"><?=L( 'No' )?></option>
                                  <option value="1"><?=L( 'Yes' )?></option>
                                </select>
                              </div>
                              <div class="form-group">
                                <label class="mb-1"><?=L( 'Maximum inserts/replacements' )?></label>
                                <input type="text" class="form-control form-control-sm input-custom-limit" value="1" name="settings[ARCHIVARIX_INCLUDE_CUSTOM][LIMIT][]">
                              </div>
                              <div class="form-group">
                                <label class="mb-1"><?=L( 'Insert' )?></label>
                                <select class="form-control form-control-sm select-custom-position" name="settings[ARCHIVARIX_INCLUDE_CUSTOM][POSITION][]">
                                  <option value="-1"><?=L( 'Before the keyphrase' )?></option>
                                  <option value="0"><?=L( 'Replace the keyphrase' )?></option>
                                  <option value="1"><?=L( 'After the keyphrase' )?></option>
                                </select>
                              </div>
                            </div>
                          </div>
                        </div>

                        <div class="">
                          <button type="button" class="btn btn-sm btn-success" id="create-loader-custom-rule">
                            <i class="fas fa-plus-square fa-fw"></i> <?=L( 'Add new rule' )?>
                          </button>
                        </div>
                      </div>
                      <div class="form-group form-check">
                        <input type="hidden" name="settings[ARCHIVARIX_FIX_MISSING_IMAGES]" value="0">
                        <input class="form-check-input" type="checkbox" name="settings[ARCHIVARIX_FIX_MISSING_IMAGES]" value="1" id="loader_settings_images" <?=$LOADER['ARCHIVARIX_FIX_MISSING_IMAGES'] ? 'checked' : ''?>>
                        <label class="form-check-label" for="loader_settings_images"><?=L( 'Fix missing images' )?></label>
                        <small class="form-text text-muted"><?=L( 'This will show 1x1 pixel transparent png for all missing images instead of 404 error.' )?></small>
                      </div>
                      <div class="form-group form-check">
                        <input type="hidden" name="settings[ARCHIVARIX_FIX_MISSING_CSS]" value="0">
                        <input class="form-check-input" type="checkbox" name="settings[ARCHIVARIX_FIX_MISSING_CSS]" value="1" id="loader_settings_css" <?=$LOADER['ARCHIVARIX_FIX_MISSING_CSS'] ? 'checked' : ''?>>
                        <label class="form-check-label" for="loader_settings_css"><?=L( 'Fix missing .css' )?></label>
                        <small class="form-text text-muted"><?=L( 'This will show empty response for all missing css styles instead of 404 error.' )?></small>
                      </div>
                      <div class="form-group form-check">
                        <input type="hidden" name="settings[ARCHIVARIX_FIX_MISSING_JS]" value="0">
                        <input class="form-check-input" type="checkbox" name="settings[ARCHIVARIX_FIX_MISSING_JS]" value="1" id="loader_settings_js" <?=$LOADER['ARCHIVARIX_FIX_MISSING_JS'] ? 'checked' : ''?>>
                        <label class="form-check-label" for="loader_settings_js"><?=L( 'Fix missing .js' )?></label>
                        <small class="form-text text-muted"><?=L( 'This will show empty response for all missing javascripts instead of 404 error.' )?></small>
                      </div>
                      <div class="form-group form-check">
                        <input type="hidden" name="settings[ARCHIVARIX_FIX_MISSING_ICO]" value="0">
                        <input class="form-check-input" type="checkbox" name="settings[ARCHIVARIX_FIX_MISSING_ICO]" value="1" id="loader_settings_ico" <?=$LOADER['ARCHIVARIX_FIX_MISSING_ICO'] ? 'checked' : ''?>>
                        <label class="form-check-label" for="loader_settings_ico"><?=L( 'Fix missing .ico' )?></label>
                        <small class="form-text text-muted"><?=L( 'This will show transparent icon for all missing .ico (i.e. favicon.ico) instead of 404 error.' )?></small>
                      </div>
                      <div class="form-group">
                        <label for="loader_settings_redirect"><?=L( 'Redirect missing pages' )?></label>
                        <input class="form-control" type="text" name="settings[ARCHIVARIX_REDIRECT_MISSING_HTML]" placeholder="" value="<?=htmlspecialchars( $LOADER['ARCHIVARIX_REDIRECT_MISSING_HTML'] )?>" id="loader_settings_redirect">
                        <small class="form-text text-muted"><?=L( '301-redirect for all missing pages to save backlink juice.' )?>
                        </small>
                      </div>
                      <div class="form-group">
                        <label for="loader_settings_maxage"><?=L( 'Max-age for static files' )?></label>
                        <input class="form-control" type="number" name="settings[ARCHIVARIX_CACHE_CONTROL_MAX_AGE]" placeholder="" pattern="[\d]{1,}" value="<?=htmlspecialchars( $LOADER['ARCHIVARIX_CACHE_CONTROL_MAX_AGE'] )?>" id="loader_settings_maxage" required>
                        <small class="form-text text-muted"><?=L( 'Leverage browser caching in seconds for static file types.' )?></small>
                      </div>
                      <div class="form-group">
                        <label for="loader_settings_sitemap"><?=L( 'Sitemap path' )?></label>
                        <input class="form-control" type="text" name="settings[ARCHIVARIX_SITEMAP_PATH]" placeholder="" value="<?=htmlspecialchars( $LOADER['ARCHIVARIX_SITEMAP_PATH'] )?>" id="loader_settings_sitemap">
                        <small class="form-text text-muted"><?=L( 'Enter a path (i.e. /sitemap.xml) to response with up-to-date sitemap.' )?>
                        </small>
                      </div>
                      <div class="form-group">
                        <label for="loader_settings_content"><?=L( 'Content directory name' )?></label>
                        <input class="form-control" type="text" name="settings[ARCHIVARIX_CONTENT_PATH]" placeholder="" value="<?=htmlspecialchars( $LOADER['ARCHIVARIX_CONTENT_PATH'] )?>" id="loader_settings_content">
                        <small class="form-text text-muted"><i class="fas fa-exclamation-triangle fa-fw"></i> <?=L( 'Leave empty in most cases.' )?>  <?=L( 'Set a custom directory name instead of .content.xxxxxxxx if you named it differently or you have multiple content directories.' )?>
                        </small>
                      </div>
                      <div class="form-group">
                        <label for="loader_settings_domain"><?=L( 'Custom domain' )?></label>
                        <input class="form-control" type="text" name="settings[ARCHIVARIX_CUSTOM_DOMAIN]" pattern="[-a-z\d.]*" placeholder="" value="<?=htmlspecialchars( $LOADER['ARCHIVARIX_CUSTOM_DOMAIN'] )?>" id="loader_settings_domain">
                        <small class="form-text text-muted"><i class="fas fa-exclamation-triangle fa-fw"></i> <?=L( 'Leave empty in most cases.' )?> <?=L( 'Set to run the original website on its subdomain or to enable subdomains on another domain.' )?>
                        </small>
                      </div>
                      <div class="form-group form-check">
                        <input type="hidden" name="settings[ARCHIVARIX_CATCH_MISSING]" value="0">
                        <input class="form-check-input" type="checkbox" name="settings[ARCHIVARIX_CATCH_MISSING]" value="1" id="loader_settings_catch_missing" <?=$LOADER['ARCHIVARIX_CATCH_MISSING'] ? 'checked' : ''?>>
                        <label class="form-check-label" for="loader_settings_catch_missing"><?=L( 'Gather missing requests' )?></label>
                        <small class="form-text text-muted"><i class="fas fa-exclamation-triangle fa-fw"></i> <?=L( 'This feature is experimental. You can view all gathered requests from visitors for missing URLs.' )?>
                        </small>
                      </div>
                      <div class="text-right">
                        <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                        <input type="hidden" name="action" value="set.loader.settings">
                        <button type="submit" class="btn btn-primary"><?=L( 'Save' )?></button>
                      </div>
                    </form>

                    <?php if ( !inSafeMode() ) { ?>
                      <div class="d-flex align-items-center text-secondary small text-uppercase my-3">
                        <div class="w-100">
                          <hr class="my-0">
                        </div>
                        <div class="mx-2 text-nowrap"><?=L( 'or' )?></div>
                        <div class="w-100">
                          <hr class="my-0">
                        </div>
                      </div>

                      <div class="row">
                        <div class="col-12 col-sm-auto mb-3">
                          <form class="d-inline" action="" method="post">
                            <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                            <input type="hidden" name="action" value="download.loader.json">
                            <button class="btn btn-primary btn-block" type="submit">
                              <i class="fas fa-file-download fw-fw"></i> <?=L( 'Download' )?></button>
                          </form>
                        </div>
                        <div class="col mb-3">
                          <form action="" method="post" id="form_import_loader_json" enctype="multipart/form-data" class="d-inline needs-validation" novalidate>
                            <div class="input-group">
                              <div class="custom-file">
                                <input type="file" class="custom-file-input input-upload-file" name="file" accept=".json,application/json" id="input_loader_file" aria-describedby="button_import_file" required>
                                <label class="custom-file-label text-truncate" for="input_loader_file"><?=L( 'Choose JSON file with settings' )?></label>
                              </div>
                              <div class="input-group-append">
                                <button class="btn btn-primary" type="submit" id="button_import_file">
                                  <i class="fas fa-file-upload fw-fw"></i> <?=L( 'Upload' )?></button>
                              </div>
                            </div>
                            <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                            <input type="hidden" name="action" value="upload.loader.json">
                          </form>
                        </div>
                      </div>
                    <?php } ?>

                  </div>


                  <div class="tab-pane fade pb-0 <?=( $subSection == 'custom' ) ? 'show active' : ''?>" id="settings_includes" role="tabpanel" aria-labelledby="settings_includes_tab">
                    <?php if ( !strlen( ACMS_PASSWORD ) && !strlen( $ACMS['ACMS_PASSWORD'] ) && !strlen( ACMS_SAFE_PASSWORD ) && !strlen( $ACMS['ACMS_SAFE_PASSWORD'] ) ) { ?>
                      <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle fa-fw"></i> <?=L( 'This section is available only when access is restricted by a password Please, set your password first.' )?>
                      </div>
                    <?php } else { ?>
                      <div class="mb-3">
                        <form action="<?=htmlspecialchars( $_SERVER['REQUEST_URI'] )?>" method="post" enctype="multipart/form-data" class="dropzone w-100" style="min-height: inherit;" id="customFileUpload">
                          <div class="dz-message m-0">
                            <i class="fas fa-file-upload fa-fw fa-2x"></i><br> <?=L( 'Drop file here to upload.' )?>
                          </div>
                          <div class="fallback">
                            <input name="file" type="file" multiple/>
                          </div>
                          <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                          <input type="hidden" name="action" value="upload.custom.file"/>
                          <input type="hidden" name="urlID" value="<?=!empty( $metaData['rowid'] ) ? $metaData['rowid'] : ''?>"/>
                          <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                          <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
                        </form>
                      </div>

                      <?php if ( empty( $customFileMeta ) ) { ?>
                        <div class="mb-3">
                          <button type="button" class="btn btn-sm btn-success" data-toggle="collapse" data-target="#div_create_custom_file" aria-expanded="true" aria-controls="div_create_custom_file" role="button">
                            <i class="fas fa-plus-square fa-fw"></i> <?=L( 'Create' )?></button>
                          <div class="mb-3 collapse" id="div_create_custom_file">
                            <hr>
                            <form action="" method="post" id="formCreateCustomFile" class="needs-validation" novalidate>
                              <div class="form-group">
                                <label><?=L( 'Filename' )?></label>
                                <div class="input-group">
                                  <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="far fa-file fa-fw"></i></span>
                                  </div>
                                  <input type="text" class="form-control" name="filename" value="" placeholder="<?=L( 'Enter filename' )?>" required>
                                </div>
                              </div>
                              <div class="form-group">
                                <label><?=L( 'Content' )?></label>
                                <textarea id="textarea_text" class="d-none" name="content"></textarea>
                              </div>
                              <div class="text-right">
                                <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                                <input type="hidden" name="action" value="create.custom.file"/>
                                <button type="submit" class="btn btn-primary">
                                  <i class="fas fa-save fa-fw"></i> <?=L( 'Save' )?></button>
                              </div>
                            </form>
                          </div>
                        </div>
                      <?php } ?>

                      <?php
                      $customFiles = getCustomFiles();
                      if ( !empty( $customFiles ) ) { ?>
                        <table class="table table-hover table-sm table-responsive">
                          <thead>
                          <tr class="bg-dark text-light">
                            <th class="text-center"><?=L( 'Actions' )?></th>
                            <th><?=L( 'Name' )?></th>
                            <th class="text-center"><?=L( 'Size' )?></th>
                            <th class="d-none d-md-table-cell align-middle text-center"><?=L( 'Modified' )?></th>
                            <th class="d-none d-md-table-cell align-middle text-center"><?=L( 'Permissions' )?></Th>
                          </tr>
                          </thead>
                          <?php foreach ( $customFiles as $customFile ) { ?>
                            <tr class="text-monospace">
                              <td class="text-nowrap">
                                <?php if ( !$customFile['is_dir'] ) { ?>
                                  <form class="d-inline" action="" method="post" id="form_remove_custom_file_<?=$customFile['id']?>">
                                    <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                                    <input type="hidden" name="action" value="delete.custom.file">
                                    <input type="hidden" name="filename" value="<?=htmlspecialchars( $customFile['filename'] )?>">
                                    <button type="button" class="btn btn-danger btn-sm btn-action" data-toggle="modal" data-target="#confirm-action" data-source="form_remove_custom_file_<?=$customFile['id']?>">
                                      <i class="fas fa-trash-alt fa-fw"></i></button>
                                  </form>
                                <?php } ?>
                                <?php if ( !$customFile['is_dir'] && $customFile['mime']['folder'] == 'html' ) { ?>
                                  <form class="d-inline" action="" method="post" id="form_custom_file_<?=$customFile['id']?>">
                                    <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                                    <input type="hidden" name="action" value="edit.custom.file">
                                    <input type="hidden" name="filename" value="<?=htmlspecialchars( $customFile['filename'] )?>">
                                    <button class="btn btn-primary btn-sm"><i class="fas fa-edit fa-fw"></i></button>
                                  </form>
                                <?php } ?>
                              </td>
                              <td class="w-100 text-nowrap align-middle">
                                <i class="far <?=$customFile['mime']['icon']?> fa-fw"></i> <?=htmlspecialchars( $customFile['filename'] )?>
                              </td>
                              <td class="align-middle text-center"><?=$customFile['is_dir'] ? '' : getHumanSize( $customFile['size'], 0 )?></td>
                              <td class="d-none d-md-table-cell align-middle text-nowrap text-center" title="<?=date( 'Y-m-d H:i:s', $customFile['mtime'] )?>"><?=date( 'Y-m-d', $customFile['mtime'] )?></td>
                              <td class="d-none d-md-table-cell align-middle text-center"><?=$customFile['permissions']?></td>
                            </tr>
                          <?php } ?>
                        </table>

                        <?php if ( isset( $customFileMeta ) ) { ?>
                          <div class="mb-3">
                            <hr>
                            <form action="" method="post" id="formCustomFile" class="needs-validation" novalidate>
                              <div class="form-group">
                                <label><?=L( 'Filename' )?></label>
                                <div class="input-group">
                                  <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="far <?=$customFile['mime']['icon']?> fa-fw"></i></span>
                                  </div>
                                  <input type="text" class="form-control" name="new_filename" value="<?=htmlspecialchars( $customFileMeta['filename'] )?>" placeholder="<?=L( 'Enter filename' )?>" required>
                                </div>
                              </div>
                              <div class="form-group">
                                <label><?=L( 'Content' )?></label>
                                <textarea id="textarea_text" class="d-none" name="content"><?=htmlspecialchars( $customFileMeta['data'] );?></textarea>
                              </div>
                              <div class="text-right">
                                <input type="hidden" name="filename" value="<?=htmlspecialchars( $customFileMeta['filename'] )?>"/>
                                <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                                <input type="hidden" name="action" value="update.custom.file"/>
                                <button type="submit" class="btn btn-primary">
                                  <i class="fas fa-save fa-fw"></i> <?=L( 'Save' )?></button>
                              </div>
                            </form>
                          </div>
                        <?php }
                      }
                    } ?>
                  </div>


                </div>
              </div>
            </div>
          <?php }

          if ( $section == 'template' && !empty( $template ) ) { ?>
            <div class="row">
              <div class="col-12 col-md-9 mb-3">
                <div class="border rounded p-3 h-100">
                  <form action="" method="post" class="needs-validation" novalidate>
                    <div class="row">
                      <div class="col-12 col-md-6">
                        <div class="form-group row">
                          <label class="col-sm-2 col-form-label col-form-label-sm text-nowrap"><?=L( 'Name' )?></label>
                          <div class="col-sm-10">
                            <input type="text" class="form-control form-control-sm" name="name" value="<?=htmlspecialchars( $template['name'], ENT_IGNORE )?>" pattern="[A-Za-z0-9-_]{1,64}" required>
                          </div>
                        </div>
                      </div>
                      <div class="col-12 col-md-6">
                        <div class="form-group row">
                          <label class="col-sm-2 col-form-label col-form-label-sm text-nowrap"><?=L( 'Hostname' )?></label>
                          <div class="col-sm-10">
                            <input type="text" class="form-control form-control-sm" name="hostname" value="<?=htmlspecialchars( $template['hostname'], ENT_IGNORE )?>" required>
                          </div>
                        </div>
                      </div>
                      <div class="col-12 col-md-6">
                        <div class="form-group row">
                          <label class="col-sm-2 col-form-label col-form-label-sm text-nowrap"><?=L( 'MIME-type' )?></label>
                          <div class="col-sm-10">
                            <input type="text" class="form-control form-control-sm" name="mimetype" value="<?=htmlspecialchars( $template['mimetype'], ENT_IGNORE )?>" required>
                          </div>
                        </div>
                      </div>
                      <div class="col-12 col-md-6">
                        <div class="form-group row">
                          <label class="col-sm-2 col-form-label col-form-label-sm text-nowrap"><?=L( 'Charset' )?></label>
                          <div class="col-sm-10">
                            <input type="text" class="form-control form-control-sm" name="charset" value="<?=htmlspecialchars( $template['charset'], ENT_IGNORE )?>">
                          </div>
                        </div>
                      </div>
                      <div class="col-12 col-md-6">
                        <div class="form-group row">
                          <label class="col-sm-2 col-form-label col-form-label-sm text-nowrap"><?=L( 'Page path' )?></label>
                          <div class="col-sm-10 constructor-wrapper">
                            <div class="input-group input-group-sm">
                              <input type="text" class="form-control form-control-sm input-constructor text-monospace border-right-0" name="path" value="<?=htmlspecialchars( $template['path'], ENT_IGNORE )?>" pattern="[/].*" required>
                              <div class="input-group-append">
                                <span class="input-group-text bg-white input-clear" type="button"><i class="fas fa-times"></i></span>
                              </div>
                            </div>
                            <span class="form-text">
                              <span class="badge badge-pill badge-dark constructor-param" type="button" data-value="%year%" data-slash="true">%year%</span>
                              <span class="badge badge-pill badge-dark constructor-param" type="button" data-value="%month%" data-slash="true">%month%</span>
                              <span class="badge badge-pill badge-dark constructor-param" type="button" data-value="%day%" data-slash="true">%day%</span>
                              <span class="badge badge-pill badge-dark constructor-param" type="button" data-value="%hour%" data-slash="true">%hour%</span>
                              <span class="badge badge-pill badge-dark constructor-param" type="button" data-value="%minute%" data-slash="true">%minute%</span>
                              <span class="badge badge-pill badge-dark constructor-param" type="button" data-value="%second%" data-slash="true">%second%</span>
                              <?php foreach ( $template['params'] as $param ) {
                                if ( $param[0]['type'] != 'STRING' ) continue; ?>
                                <span class="badge badge-pill alert-success constructor-param" type="button" data-value="%<?=$param[0]['name']?>%" data-slash="true">%<?=$param[0]['name']?>%</span>
                              <?php } ?>
                            </span>
                          </div>
                        </div>
                      </div>
                      <div class="col-12 col-md-6">
                        <div class="form-group row">
                          <label class="col-sm-2 col-form-label col-form-label-sm text-nowrap"><?=L( 'Uploads' )?></label>
                          <div class="col-sm-10 constructor-wrapper">
                            <div class="input-group input-group-sm">
                              <input type="text" class="form-control form-control-sm input-constructor text-monospace border-right-0" name="uploads" value="<?=htmlspecialchars( $template['uploads'], ENT_IGNORE )?>" pattern="[/].*" required>
                              <div class="input-group-append">
                                <span class="input-group-text bg-white input-clear" type="button"><i class="fas fa-times"></i></span>
                              </div>
                            </div>
                            <span class="form-text">
                              <span class="badge badge-pill badge-dark constructor-param" type="button" data-value="%md5%" data-slash="true">%md5%</span>
                              <span class="badge badge-pill badge-dark constructor-param" type="button" data-value="%filename%" data-slash="true">%filename%</span>
                              <span class="badge badge-pill badge-dark constructor-param" type="button" data-value="%ext%" data-slash="false">%ext%</span>
                              <span class="badge badge-pill badge-dark constructor-param" type="button" data-value="%year%" data-slash="true">%year%</span>
                              <span class="badge badge-pill badge-dark constructor-param" type="button" data-value="%month%" data-slash="true">%month%</span>
                              <span class="badge badge-pill badge-dark constructor-param" type="button" data-value="%day%" data-slash="true">%day%</span>
                              <span class="badge badge-pill badge-dark constructor-param" type="button" data-value="%hour%" data-slash="true">%hour%</span>
                              <span class="badge badge-pill badge-dark constructor-param" type="button" data-value="%minute%" data-slash="true">%minute%</span>
                              <span class="badge badge-pill badge-dark constructor-param" type="button" data-value="%second%" data-slash="true">%second%</span>
                              <?php foreach ( $template['params'] as $param ) {
                                if ( $param[0]['type'] != 'STRING' ) continue; ?>
                                <span class="badge badge-pill alert-success constructor-param" type="button" data-value="%<?=$param[0]['name']?>%" data-slash="true">%<?=$param[0]['name']?>%</span>
                              <?php } ?>
                            </span>
                          </div>
                        </div>
                      </div>
                      <div class="col-12">
                        <div class="d-md-flex h-100 align-items-end justify-content-end">
                          <div class="text-center text-md-right">
                            <button type="submit" class="btn btn-sm btn-primary">
                              <i class="fas fa-save fa-fw"></i> <?=L( 'Save settings only' )?></button>
                            <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                            <input type="hidden" name="action" value="update.template.settings"/>
                            <input type="hidden" name="name_orig" value="<?=$template['name']?>"/>
                            <input type="hidden" name="show" value="edit.template"/>
                            <input type="hidden" name="urlID" value="<?=!empty( $metaData['rowid'] ) ? $metaData['rowid'] : ''?>"/>
                            <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                            <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
                          </div>
                        </div>
                      </div>
                    </div>
                  </form>
                </div>
              </div>


              <div class="col-12 col-md-3 mb-3">
                <div class="border p-3 rounded h-100">
                  <div class="row">
                    <div class="col pr-0">
                      <form class="d-inline" id="" action="" method="post">
                        <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                        <input type="hidden" name="action" value="show.create.page">
                        <input type="hidden" name="name" value="<?=$template['name']?>">
                        <button class="btn btn-primary btn-block">
                          <i class="far fa-plus-square fa-fw"></i> <?=L( 'Create page' )?></button>
                      </form>
                    </div>
                    <div class="col-auto">
                      <form class="d-inline" id="form_template_remove" action="" method="post">
                        <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                        <input type="hidden" name="action" value="template.remove">
                        <input type="hidden" name="name" value="<?=$template['name']?>">
                        <button type="button" class="btn btn-danger btn-action" data-toggle="modal" data-target="#confirm-action" data-source="form_template_remove" title="<?=L( 'Remove template' )?>">
                          <i class="fas fa-trash-alt fa-fw"></i></button>
                      </form>
                    </div>
                  </div>
                  <?php if ( !empty( $template['params'] ) ) { ?>
                    <div class="mt-2">
                      <div class="d-flex align-items-center">
                        <div class="text-uppercase text-nowrap small muted mr-2"><?=L( 'Found parameters' )?></div>
                        <div class="w-100 ml-auto">
                          <hr>
                        </div>
                      </div>
                      <?php foreach ( $template['params'] as $paramName => $param ) { ?>
                        <button type="button" class="btn alert-success btn-sm badge-pill text-monospace mb-1">
                          <i class="fas fa-fw <?=$param[0]['icon']?>"></i>
                          <?=htmlspecialchars( $paramName )?>
                          <?php if ( count( $param ) > 1 ) { ?>
                            <span class="badge badge-light badge-pill"><?=count( $param )?></span><?php } ?>
                        </button>
                      <?php } ?>
                    </div>
                  <?php } ?>
                </div>
              </div>

            </div>

            <div>
              <ul class="nav nav-tabs mb-3" role="tablist">
                <li class="nav-item">
                  <a class="nav-link" id="wysiwyg-tab" data-toggle="tab" href="#wysiwyg" role="tab" aria-controls="wysiwyg" aria-selected="true"><?=L( 'WYSIWYG' )?></a>
                </li>
                <li class="nav-item">
                  <a class="nav-link active" id="code-tab" data-toggle="tab" href="#code" role="tab" aria-controls="code" aria-selected="false"><?=L( 'Code' )?></a>
                </li>
              </ul>
              <div class="tab-content">
                <div class="tab-pane fade" id="wysiwyg" role="tabpanel" aria-labelledby="wysiwyg-tab">
                  <form action="" method="post" onsubmit="ajaxSaveFile('formHtml'); return false;" id="formHtml">
                    <textarea id="textarea_html" name="content" class="w-100 h-100"><?=htmlspecialchars( convertEncoding( $template['content'], 'UTF-8', $template['charset'] ) );?></textarea>
                    <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                    <input type="hidden" name="action" value="update.template.content"/>
                    <input type="hidden" name="show" value="edit.template"/>
                    <input type="hidden" name="name" value="<?=htmlspecialchars( $template['name'] )?>"/>
                    <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                    <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
                    <input type="hidden" name="ajax" value="1"/>
                    <button type="submit" class="btn btn-primary float-right my-3">
                      <i class="fas fa-save fa-fw"></i> <?=L( 'Save' )?></button>
                  </form>
                </div>
                <div class="tab-pane fade show active" id="code" role="tabpanel" aria-labelledby="code-tab">
                  <form action="" method="post" onsubmit="ajaxSaveFile('formCode'); return false;" id="formCode">
                    <textarea id="textarea_text" name="content"><?=htmlspecialchars( convertEncoding( $template['content'], 'UTF-8', $template['charset'] ) );?></textarea>
                    <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                    <input type="hidden" name="action" value="update.template.content"/>
                    <input type="hidden" name="show" value="edit.template"/>
                    <input type="hidden" name="name" value="<?=htmlspecialchars( $template['name'] )?>"/>
                    <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                    <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
                    <input type="hidden" name="ajax" value="1"/>
                    <div class="text-right" style="display: block; position: fixed; bottom: 30px; right: 30px; z-index: 99;">
                      <div class="row" id="template-param-panel">
                        <div class="col-auto alert-info p-2 mr-3 w-auto rounded">
                          <div class="d-flex">
                            <div class="text-monospace">
                              {{@<span id="template-param-type">STRING</span>('<input type="text" id="template-param-name" class="border-0 bg-transparent text-success text-center text-monospace d-inline input-resize-auto" value="name" size="4" pattern="[-A-Za-z0-9]+" onkeypress="return /[-a-z0-9]+/i.test(event.key)" onkeydown="return event.key != 'Enter';" onClick="this.select();">')}}
                            </div>
                            <div class="ml-auto">
                              <button type="button" class="btn btn-success btn-sm ml-2" id="template-param-insert" title="<?=L( 'Insert' )?>">
                                <i class="fas fa-level-down-alt fa-fw fa-rotate-90"></i></button>
                            </div>
                          </div>
                          <hr class="my-1">
                          <div class="text-center">
                            <button type="button" class="btn btn-success btn-sm badge-pill" data-type="STRING">
                              <i class="fas fa-fw fa-align-left"></i> STRING
                            </button>
                            <button type="button" class="btn btn-success btn-sm badge-pill" data-type="HTML">
                              <i class="fas fa-fw fa-code"></i> HTML
                            </button>
                            <button type="button" class="btn btn-success btn-sm badge-pill" data-type="FILE">
                              <i class="fas fa-fw fa-file"></i> FILE
                            </button>
                            <button type="button" class="btn btn-success btn-sm badge-pill" data-type="URL" data-name="path">
                              <i class="fas fa-fw fa-link"></i> URL
                            </button>
                          </div>
                        </div>
                      </div>
                      <button type="submit" class="btn btn-primary mt-3" id="cmSave">
                        <i class="fas fa-save fa-fw"></i> <?=L( 'Save' )?></button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          <?php }

          if ( $section == 'page' && !empty( $templateInfo ) ) { ?>
            <h2><?=sprintf( L( 'New page based on <i>%s</i> template' ), htmlspecialchars( $templateInfo['name'] ) )?></h2>
            <div class="mt-3">
              <form action="" method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                <div class="form-group">
                  <label for="input_uri_address"><?=L( 'URI address' )?></label>
                  <input class="form-control" type="text" name="path" value="<?=$template['path']?>" placeholder="<?=L( 'Enter a path, e.g. /page.html' )?>" pattern="[/].*" id="input_uri_address" required>
                  <div class="custom-control custom-checkbox">
                    <input type="hidden" name="path_latin" value="0">
                    <input class="custom-control-input" type="checkbox" name="path_latin" id="page_path_latin" checked value="1">
                    <label class="custom-control-label" for="page_path_latin"><?=L( 'Transliterate to latin characters' )?></label>
                  </div>
                </div>
                <?php foreach ( $templateInfo['params'] as $name => $data ) {
                  if ( in_array( $data[0]['type'], ['URL'] ) ) continue;
                  ?>
                  <div class="form-group">
                    <label for="input_<?=htmlspecialchars( $name )?>"><?=L( 'Parameter' )?>
                      <i><?=htmlspecialchars( $name )?></i></label>
                    <?php switch ( strtoupper( $data[0]['type'] ) ) {
                      case 'STRING' :
                        echo '<input type="text" class="form-control" name="params[' . htmlspecialchars( $name ) . ']" id="input_' . htmlspecialchars( $name ) . '">';
                        break;
                      case 'HTML' :
                        echo '<textarea class="form-control tinymce-control" name="params[' . htmlspecialchars( $name ) . ']" id="input_' . htmlspecialchars( $name ) . '"></textarea>';
                        break;
                      case 'FILE' :
                        ?>
                        <div class="input-group">
                          <div class="custom-file">
                            <input type="file" class="custom-file-input input-upload-file" name="params[<?=htmlspecialchars( $name )?>]" id="input_<?=htmlspecialchars( $name )?>" aria-describedby="button_import_file">
                            <label class="custom-file-label text-truncate" for="input_import_file"><?=L( 'Choose file' )?></label>
                          </div>
                          <div class="input-group-append">
                            <button class="btn btn-primary" type="submit" id="button_import_file"><?=L( 'Upload' )?></button>
                          </div>
                        </div>
                      <?php
                      default :
                    } ?>
                  </div>
                <?php } ?>
                <input type="hidden" name="name" value="<?=htmlspecialchars( $templateInfo['name'] )?>">
                <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                <input type="hidden" name="action" value="create.template.page"/>
                <div class="text-right">
                  <button type="submit" class="btn btn-primary"><?=L( 'Create' )?></button>
                </div>
              </form>
            </div>
          <?php }

          if ( $section == 'backups' ) { ?>
            <div class="border p-0 mb-3 rounded">
              <div class="row m-3">
                <div class="col-12 col-sm-auto p-1">
                  <button type="button" class="btn btn-primary m-1 btn-block" data-toggle="modal" data-target="#breakpointModal">
                    <i class="fas fa-plus fa-fw"></i> <?=L( 'New breakpoint' )?></button>
                </div>
                <div class="col-12 col-sm-auto p-1">
                  <button type="button" class="btn btn-primary m-1 btn-block" id="historyRecoverSelected">
                    <i class="fas fa-undo fa-fw"></i> <?=L( 'Roll back selected' )?></button>
                </div>
                <?php
                $breakpoints = getBackupsBreakpoints();
                if ( !empty( $breakpoints ) ) { ?>
                  <div class="col-12 col-sm-auto p-1">
                    <div class="btn-group m-1 btn-block" role="group">
                      <button id="rollbackGroup" type="button" class="btn btn-primary btn-block dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-undo fa-fw"></i> <?=L( 'Roll back to a breakpoint' )?>
                      </button>
                      <div class="dropdown-menu" style="max-width:400px;" aria-labelledby="rollbackGroup">
                        <h6 class="dropdown-header"><?=L( 'Choose the breakpoint' )?></h6>
                        <?php foreach ( $breakpoints as $breakpoint ) { ?>
                          <div class="dropdown-divider"></div>
                          <div class="d-flex align-items-center">
                            <div class="text-truncate px-0">
                              <form action="" method="post" class="d-inline" id="form_history_recover_breakpoint_<?=$breakpoint['rowid']?>">
                                <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                                <input type="hidden" name="action" value="history.recover">
                                <input type="hidden" name="breakpoint" value="<?=$breakpoint['rowid']?>">
                                <button type="submit" class="dropdown-item text-truncate px-2" href="#" title="<?=L( 'Rollback to' )?>: <?=htmlspecialchars( $breakpoint['name'] )?> (<?=getHumanTime( time() - $breakpoint['created'], 1 ) . ' ' . L( 'ago' )?>)">
                                  <i class="fas fa-arrow-right fa-fw"></i><i class="fas fa-circle text-danger fa-fw"></i> <?=htmlspecialchars( $breakpoint['name'] )?>
                                  <small>
                                    (<?=getHumanTime( time() - $breakpoint['created'], 1 ) . ' ' . L( 'ago' )?>)
                                  </small>
                                </button>
                              </form>
                            </div>
                            <div class="ml-auto border-left px-2 text-nowrap">
                              <form action="" method="post" class="d-inline" id="form_history_purge_breakpoint_<?=$breakpoint['rowid']?>">
                                <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                                <input type="hidden" name="action" value="history.purge">
                                <input type="hidden" name="breakpoint" value="<?=$breakpoint['rowid']?>">
                                <button type="submit" class="btn btn-sm btn-danger" title="<?=L( 'Purge history up to' )?>: <?=htmlspecialchars( $breakpoint['name'] )?>">
                                  <i class="far fa-trash-alt"></i></button>
                                <i class="fas fa-arrow-left fa-fw"></i>
                              </form>
                            </div>
                          </div>
                        <?php } ?>
                      </div>
                    </div>
                  </div>
                <?php } ?>
                <div class="col-12 col-sm-auto p-1">
                  <form class="d-inline" id="form_history_recover_all" action="" method="post">
                    <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                    <input type="hidden" name="action" value="history.recover">
                    <input type="hidden" name="all" value="1">
                    <button type="submit" class="btn btn-primary m-1 btn-block">
                      <i class="fas fa-fast-backward fa-fw"></i> <?=L( 'Roll back all' )?></button>
                  </form>
                </div>
                <div class="col-12 col-sm-auto p-1">
                  <button type="button" class="btn btn-danger m-1 btn-block" id="historyPurgeSelected">
                    <i class="fas fa-trash-alt fa-fw"></i> <?=L( 'Purge selected' )?></button>
                </div>
                <div class="col-12 col-sm-auto p-1">
                  <form class="d-inline" id="form_history_purge_all" action="" method="post">
                    <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                    <input type="hidden" name="action" value="history.purge">
                    <input type="hidden" name="all" value="1">
                    <button type="button" class="btn btn-danger m-1 btn-block btn-action" data-toggle="modal" data-target="#confirm-action" data-source="form_history_purge_all">
                      <i class="fas fa-trash-alt fa-fw"></i> <?=L( 'Purge all' )?></button>
                  </form>
                </div>
                <?php $imports = getImportsList();
                if ( !empty( $imports ) ) { ?>
                  <div class="col-12 col-sm-auto p-1">
                    <div class="btn-group m-1 btn-block dropdown" role="group">
                      <button id="reinstallGroup" type="button" class="btn btn-danger btn-block dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-sync fa-fw"></i> <?=L( 'Reinstall' )?>
                      </button>
                      <div class="dropdown-menu pt-0" style="max-width:400px;" aria-labelledby="rollbackGroup">
                        <div class="dropdown-header alert-warning" style="white-space: inherit;"><?=L( 'Attention! Reinstall will delete and replace all your website data urls and its content.' )?></div>
                        <?php foreach ( $imports as $import ) { ?>
                          <form action="" method="post" class="d-inline" id="form_import_<?=$import['id']?>">
                            <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                            <input type="hidden" name="action" value="import.perform.reinstall">
                            <input type="hidden" name="filename" value="<?=$import['filename']?>">
                            <div class="dropdown-divider m-0"></div>
                            <div class="dropdown-item p-3">
                              <div class="position-relative">
                                <img src="<?=$import['screenshot']?>" class="img-fluid w-100 border border-bottom-0" onerror="this.src='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAIAAAABLCAIAAAA3cxjrAAAABnRSTlMAAAAAAABupgeRAAAAMklEQVR42u3BAQEAAACCIP+vbkhAAQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAEcGcMsAAQVgyaQAAAAASUVORK5CYII=';">
                                <div class="text-center position-absolute" style="bottom:10%; left:50%; transform: translate(-50%); ">
                                  <span class="badge badge-pill badge-warning shadow py-2"><?=number_format( $import['info']['filescount'], 0 ) . ' URLs, ' . getHumanSize( $import['info']['filessize'] )?></span>
                                </div>
                              </div>
                              <button type="button" class="btn btn-danger btn-block text-left text-truncate btn-action rounded-0 py-2" title="<?=convertIdnToUtf8( $import['info']['settings']['domain'] )?>" data-toggle="modal" data-target="#confirm-action" data-source="form_import_<?=$import['id']?>" data-message="<?=L( 'Attention! Reinstall will delete and replace all your website data urls and its content.' )?>">
                                <div class="d-flex align-items-center">
                                  <div>
                                    <div class="h5"><?=convertIdnToUtf8( $import['info']['settings']['domain'] )?></div>
                                    <div class="text-monospace small"><?=implode( '-', str_split( $import['info']['settings']['uuid'], 4 ) )?></div>
                                  </div>
                                  <div class="ml-auto pl-3">
                                    <i class="fas fa-arrow-right fa-lg fa-fw"></i>
                                  </div>
                                </div>
                              </button>
                            </div>
                          </form>
                        <?php } ?>
                      </div>
                    </div>
                  </div>
                <?php } ?>
              </div>
            </div>

            <table id="table_history" data-toggle="table" data-pagination="true" data-search="true" data-show-export="false" data-page-size="50" data-page-list="[10, 25, 50, 100, 200, All]" data-click-to-select="true" data-maintain-meta-data="true" data-locale="<?=( $_SESSION['archivarix.lang'] == 'en' ? 'en-US' : 'ru-RU' )?>" data-sort-class="bg-light" data-trim-on-search="false" data-unique-id="id" class="table table-bordered table-sm">
              <thead class="thead-light">
              <tr>
                <th data-field="state" data-checkbox="true" class="px-1"></th>
                <th data-field="id" data-visible="false"></th>
                <th data-field="action" data-sortable="true" data-align="center"><?=L( 'Action' )?></th>
                <th data-field="url" data-sortable="true" data-width="100" data-width-unit="%"><?=L( 'URL' )?></th>
                <th data-field="created" data-sortable="true" data-align="center"><?=L( 'Created' )?></th>
              </tr>
              </thead>
              <tbody>
              <?php
              foreach ( $backups as $historyItem ) {
                $historySettings = json_decode( $historyItem['settings'], true );
                ?>
                <tr>
                  <td></td>
                  <td><?=$historyItem['rowid']?></td>
                  <td class="small"><?=$historyItem['action']?></td>
                  <td>
                    <?php if ( $historyItem['action'] == 'breakpoint' ) { ?>
                      <i class="fas fa-circle fa-fw text-danger"></i>
                      <mark><?=htmlspecialchars( $historySettings['name'] )?></mark>
                    <?php } else { ?>
                      <?=htmlspecialchars( rawurldecode( $historySettings['request_uri'] ), ENT_IGNORE )?>
                      <br/>
                      <small class="text-secondary"><?=convertIdnToUtf8( $historySettings['hostname'] )?></small>
                    <?php } ?>
                  </td>
                  <td class="small"><?=date( 'Y-m-d H:i:s', $historyItem['created'] )?></td>
                </tr>
              <?php } ?>
              </tr>
              </tbody>
            </table>
            <?php
          }

          if ( $section == 'htaccess' && !inSafeMode() ) { ?>
            <form action="" method="post">
              <h3><?=L( '.htaccess editor' )?></h3>
              <div class="form-group">
                <label><?=L( 'Content' )?></label>
                <textarea class="form-control codemirror-editor" data-mime="text/plain" data-readonly="false" name="htaccess"><?=htmlspecialchars( file_get_contents( __DIR__ . DIRECTORY_SEPARATOR . '.htaccess' ) )?></textarea>
              </div>
              <div class="text-right">
                <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                <input type="hidden" name="action" value="edit.htaccess">
                <input type="hidden" name="urlID" value="<?=!empty( $metaData['rowid'] ) ? $metaData['rowid'] : ''?>"/>
                <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
                <button type="submit" class="btn btn-primary"><?=L( 'Save' )?></button>
              </div>
            </form>
          <?php }

          if ( $section == 'tools' ) {
          $missingExtensions = getMissingExtensions( ['zip', 'curl'] );
          ?>
          <div class="row row-cols-1 row-cols-md-3">
            <div class="col mb-4">
              <div class="card h-100 shadow">
                <div class="card-header text-white bg-dark">
                  <?=L( 'Websites import' )?>
                </div>
                <div class="card-body small">
                  <?=L( 'Import websites created by Archivarix.' )?>
                  <?php if ( !empty( $missingExtensions ) ) { ?>
                    <div class="p-3 rounded bg-danger text-light mt-3 font-weight-bold"><?=sprintf( L( 'This tool requires following PHP extensions to be installed: %s.' ), implode( ', ', $missingExtensions ) )?></div>
                  <?php } ?>
                </div>
                <div class="card-footer text-right">
                  <form action="" method="post" id="">
                    <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                    <input type="hidden" name="action" value="import.view">
                    <input type="hidden" name="urlID" value="<?=!empty( $metaData['rowid'] ) ? $metaData['rowid'] : ''?>"/>
                    <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                    <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
                    <button type="submit" class="btn btn-sm btn-primary" <?=!empty( $missingExtensions ) ? ' disabled' : ''?>><?=L( 'Import tool' )?></button>
                  </form>
                </div>
              </div>
            </div>

            <div class="col mb-4">
              <div class="card h-100 mb-4 shadow">
                <div class="card-header text-white bg-dark">
                  <?=L( 'Website conversion to UTF-8' )?>
                </div>
                <div class="card-body small">
                  <?=L( 'This tool correctly converts to UTF-8 all html pages and other types of text files with a non-UTF-8 encoding.' )?>
                </div>
                <div class="card-footer text-right">
                  <form action="" method="post" id="form_convert_utf8">
                    <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                    <input type="hidden" name="action" value="convert.utf8">
                    <input type="hidden" name="urlID" value="<?=!empty( $metaData['rowid'] ) ? $metaData['rowid'] : ''?>"/>
                    <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                    <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
                    <button type="button" class="btn btn-sm btn-primary btn-action" data-toggle="modal" data-target="#confirm-action" data-source="form_convert_utf8">
                      <?=L( 'Convert to' )?> UTF-8
                    </button>
                  </form>
                </div>
              </div>
            </div>

            <div class="col mb-4">
              <div class="card h-100 mb-4 shadow">
                <div class="card-header text-white bg-dark">
                  <?=L( 'Allow website in robots.txt' )?>
                </div>
                <div class="card-body small">
                  <form action="" method="post" id="form_robots_allow">
                    <?=L( 'Create/overwrite robots.txt and allow crawling the whole website.' )?>
                    <div class="custom-control custom-checkbox mt-2">
                      <input id="tool_include_sitemap" type="checkbox" class="custom-control-input" name="sitemap_include" value="1" data-toggle="collapse" data-target="#tool_robots_sitemap">
                      <label class="custom-control-label" for="tool_include_sitemap"><?=L( 'Include sitemap.xml' )?></label>
                    </div>
                    <div class="collapse mt-1" id="tool_robots_sitemap">
                      <?php
                      $LOADER               = loadLoaderSettings();
                      $toolRobotsSitemapUrl =
                        ( !in_array( $LOADER['ARCHIVARIX_PROTOCOL'], ['any', ''] ) ? $LOADER['ARCHIVARIX_PROTOCOL'] :
                          ( !empty( $uuidSettings['https'] ) ? 'https' : 'http' )
                        ) .
                        '://' .
                        ( !empty( $LOADER['ARCHIVARIX_CUSTOM_DOMAIN'] ) ? $LOADER['ARCHIVARIX_CUSTOM_DOMAIN'] :
                          ( $_SERVER['HTTP_HOST'] == $uuidSettings['domain'] ? $uuidSettings['domain'] : $_SERVER['HTTP_HOST'] )
                        ) .
                        ( $LOADER['ARCHIVARIX_SITEMAP_PATH'] ? $LOADER['ARCHIVARIX_SITEMAP_PATH'] : '/sitemap.xml' );
                      ?>
                      <input type="input" class="form-control form-control-sm text-monospace" name="sitemap" value="<?=htmlspecialchars( $toolRobotsSitemapUrl )?>">
                      <span class="form-text text-muted"><?=L( 'Make sure the protocol and the domain name are correct.' )?></span>
                    </div>
                </div>
                <div class="card-footer text-right">
                  <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                  <input type="hidden" name="action" value="robots.allow">
                  <input type="hidden" name="urlID" value="<?=!empty( $metaData['rowid'] ) ? $metaData['rowid'] : ''?>"/>
                  <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                  <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
                  <button type="submit" class="btn btn-sm btn-primary"><?=L( 'Fix robots.txt' )?></button>
                  </form>
                </div>
              </div>
            </div>

            <?php
            $missingExtensions = getMissingExtensions( ['dom', 'libxml', 'mbstring'] );
            ?>
            <div class="col mb-4">
              <div class="card h-100 shadow">
                <div class="card-header text-white bg-dark">
                  <?=L( 'Versions in CSS and JS' )?>
                </div>
                <div class="card-body small">
                  <?=L( 'Some websites use version control by adding ?ver=x.x to the end of URLs. This will fix such URLs. It will also fix URLs with PHPSESSID.' )?>
                  <?php if ( !empty( $missingExtensions ) ) { ?>
                    <div class="p-3 rounded bg-danger text-light mt-3 font-weight-bold"><?=sprintf( L( 'This tool requires following PHP extensions to be installed: %s.' ), implode( ', ', $missingExtensions ) )?></div>
                  <?php } ?>
                </div>
                <div class="card-footer text-right">
                  <form action="" method="post" id="form_remove_version_control">
                    <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                    <input type="hidden" name="action" value="version.control.remove">
                    <input type="hidden" name="urlID" value="<?=!empty( $metaData['rowid'] ) ? $metaData['rowid'] : ''?>"/>
                    <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                    <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
                    <button type="button" class="btn btn-sm btn-primary btn-action" data-toggle="modal" data-target="#confirm-action" data-source="form_remove_version_control" <?=!empty( $missingExtensions ) ? ' disabled' : ''?>><?=L( 'Fix versions in URLs' )?></button>
                  </form>
                </div>
              </div>
            </div>

            <div class="col mb-4">
              <div class="card h-100 shadow">
                <div class="card-header text-white bg-dark">
                  <?=L( 'Remove broken links' )?>
                </div>
                <div class="card-body small">
                  <form action="" method="post" id="form_remove_broken_links">
                    <?=L( 'This tool will scan all internal links that lead to missing pages and remove that links while keeping anchors.' )?>
                    <div class="custom-control custom-checkbox mt-2">
                      <input id="tool_broken_links_files" type="checkbox" class="custom-control-input" name="check_files" value="1">
                      <label class="custom-control-label" for="tool_broken_links_files"><?=L( 'Additionally check files on this server' )?>
                      </label>
                    </div>
                    <?php if ( !empty( $missingExtensions ) ) { ?>
                      <div class="p-3 rounded bg-danger text-light mt-3 font-weight-bold"><?=sprintf( L( 'This tool requires following PHP extensions to be installed: %s.' ), implode( ', ', $missingExtensions ) )?></div>
                    <?php } ?>
                </div>
                <div class="card-footer text-right">
                  <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                  <input type="hidden" name="action" value="broken.links.remove">
                  <input type="hidden" name="urlID" value="<?=!empty( $metaData['rowid'] ) ? $metaData['rowid'] : ''?>"/>
                  <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                  <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
                  <button type="button" class="btn btn-sm btn-primary btn-action" data-toggle="modal" data-target="#confirm-action" data-source="form_remove_broken_links" <?=!empty( $missingExtensions ) ? ' disabled' : ''?>><?=L( 'Remove links' )?></button>
                  </form>
                </div>
              </div>
            </div>

            <div class="col mb-4">
              <div class="card h-100 shadow">
                <div class="card-header text-white bg-dark">
                  <?=L( 'Remove broken images' )?>
                </div>
                <div class="card-body small">
                  <form action="" method="post" id="form_remove_broken_images">
                    <?=L( 'This tool will scan all image tags for missing internal urls and remove those image tags.' )?>
                    <div class="custom-control custom-checkbox mt-2">
                      <input id="tool_broken_images_files" type="checkbox" class="custom-control-input" name="check_files" value="1">
                      <label class="custom-control-label" for="tool_broken_images_files"><?=L( 'Additionally check files on this server' )?>
                      </label>
                    </div>
                    <?php if ( !empty( $missingExtensions ) ) { ?>
                      <div class="p-3 rounded bg-danger text-light mt-3 font-weight-bold"><?=sprintf( L( 'This tool requires following PHP extensions to be installed: %s.' ), implode( ', ', $missingExtensions ) )?></div>
                    <?php } ?>
                </div>
                <div class="card-footer text-right">
                  <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                  <input type="hidden" name="action" value="broken.images.remove">
                  <input type="hidden" name="urlID" value="<?=!empty( $metaData['rowid'] ) ? $metaData['rowid'] : ''?>"/>
                  <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                  <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
                  <button type="button" class="btn btn-sm btn-primary btn-action" data-toggle="modal" data-target="#confirm-action" data-source="form_remove_broken_images" <?=!empty( $missingExtensions ) ? ' disabled' : ''?>><?=L( 'Remove images' )?></button>
                  </form>
                </div>
              </div>
            </div>

            <div class="col mb-4">
              <div class="card h-100 shadow">
                <div class="card-header text-white bg-dark">
                  <?=L( 'Viewport meta tag' )?>
                </div>
                <div class="card-body small">
                  <form action="" method="post" id="form_update_viewport">
                    <?=L( 'Add the viewport meta tag to all pages of the site to adapt the site to display on mobile devices.' )?>
                    <div class="custom-control custom-checkbox mt-2">
                      <input id="checkbox_custom_viewport" type="checkbox" class="custom-control-input" data-toggle="collapse" data-target="#tool_custom_viewport">
                      <label class="custom-control-label" for="checkbox_custom_viewport"><?=L( 'Custom viewport value' )?></label>
                    </div>
                    <div class="collapse mt-1" id="tool_custom_viewport">
                      <input type="input" class="form-control form-control-sm text-monospace" name="custom_viewport" value="width=device-width, initial-scale=1">
                    </div>
                    <div class="custom-control custom-checkbox mt-2">
                      <input id="tool_viewport_overwrite" type="checkbox" class="custom-control-input" name="overwrite" value="1">
                      <label class="custom-control-label" for="tool_viewport_overwrite"><?=L( 'Overwrite if already presents' )?>
                      </label>
                    </div>
                    <?php if ( !empty( $missingExtensions ) ) { ?>
                      <div class="p-3 rounded bg-danger text-light mt-3 font-weight-bold"><?=sprintf( L( 'This tool requires following PHP extensions to be installed: %s.' ), implode( ', ', $missingExtensions ) )?></div>
                    <?php } ?>
                </div>
                <div class="card-footer text-right">
                  <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                  <input type="hidden" name="action" value="update.viewport">
                  <input type="hidden" name="urlID" value="<?=!empty( $metaData['rowid'] ) ? $metaData['rowid'] : ''?>"/>
                  <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                  <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
                  <button type="button" class="btn btn-sm btn-primary btn-action" data-toggle="modal" data-target="#confirm-action" data-source="form_update_viewport" <?=!empty( $missingExtensions ) ? ' disabled' : ''?>><?=L( 'Add viewport' )?></button>
                  </form>
                </div>
              </div>
            </div>

            <div class="col mb-4">
              <div class="card h-100 shadow">
                <div class="card-header text-white bg-dark">
                  <?=L( 'Broken URLencoded links' )?>
                </div>
                <div class="card-body small">
                  <?=L( 'Some URLencode links containing non-Latin letters or characters might be encoded incorrectly. This tool will fix such links in HTML code.' )?>
                  <?php if ( !empty( $missingExtensions ) ) { ?>
                    <div class="p-3 rounded bg-danger text-light mt-3 font-weight-bold"><?=sprintf( L( 'This tool requires following PHP extensions to be installed: %s.' ), implode( ', ', $missingExtensions ) )?></div>
                  <?php } ?>
                </div>
                <div class="card-footer text-right">
                  <form action="" method="post" id="form_update_encoded_urls">
                    <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                    <input type="hidden" name="action" value="broken.encoded.urls">
                    <input type="hidden" name="urlID" value="<?=!empty( $metaData['rowid'] ) ? $metaData['rowid'] : ''?>"/>
                    <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                    <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
                    <button type="button" class="btn btn-sm btn-primary btn-action" data-toggle="modal" data-target="#confirm-action" data-source="form_update_encoded_urls" <?=!empty( $missingExtensions ) ? ' disabled' : ''?>><?=L( 'Fix URL encoding' )?></button>
                  </form>
                </div>
              </div>
            </div>

            <div class="col mb-4">
              <div class="card h-100 shadow">
                <div class="card-header text-white bg-dark">
                  <?=L( 'Work with external links' )?>
                </div>
                <div class="card-body small">
                  <?=L( 'Set rel attribute value for all internal links. E.g. make all external links nofollow.' )?>
                  <br>
                  <?=L( 'You can also remove all external links but keep the anchor text and content.' )?>
                  <?php if ( !empty( $missingExtensions ) ) { ?>
                    <div class="p-3 rounded bg-danger text-light mt-3 font-weight-bold"><?=sprintf( L( 'This tool requires following PHP extensions to be installed: %s.' ), implode( ', ', $missingExtensions ) )?></div>
                  <?php } ?>
                </div>
                <div class="card-footer small">
                  <div class="">
                    <a href="#div_update_external_links" class="expand-label text-dark" data-toggle="collapse" data-target="#div_update_external_links" aria-expanded="false" aria-controls="div_update_external_links">
                      <i class="fas fa-caret-right mr-2"></i>
                      <?=L( 'Show settings' )?>
                    </a>
                  </div>
                  <div class="collapse mt-2" id="div_update_external_links">
                    <form action="" method="post" id="form_update_external_links">
                      <div class="row">
                        <div class="col-12">
                          <div class="form-group">
                            <label>rel</label>
                            <input class="form-control form-control-sm w-100" type="text" name="attributes[rel]" value="" placeholder="<?=L( 'Leave empty for no change' )?>">
                          </div>
                          <div class="form-group">
                            <label>target</label>
                            <input class="form-control form-control-sm  w-100" type="text" name="attributes[target]" value="" placeholder="<?=L( 'Leave empty for no change' )?>">
                          </div>
                        </div>
                        <div class="col-12 text-center align-self-center">
                          <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                          <input type="hidden" name="action" value="external.links.update">
                          <input type="hidden" name="urlID" value="<?=!empty( $metaData['rowid'] ) ? $metaData['rowid'] : ''?>"/>
                          <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                          <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
                          <button type="button" class="btn btn-sm btn-primary btn-action" data-toggle="modal" data-target="#confirm-action" data-source="form_update_external_links" <?=!empty( $missingExtensions ) ? ' disabled' : ''?>><?=L( 'Update external links' )?></button>

                          <div class="d-flex align-items-center text-secondary small text-uppercase my-3">
                            <div class="w-100">
                              <hr class="my-0">
                            </div>
                            <div class="mx-2"><?=L( 'or' )?></div>
                            <div class="w-100">
                              <hr class="my-0">
                            </div>
                          </div>

                          <button type="button" class="btn btn-sm btn-danger btn-action" data-toggle="modal" data-target="#confirm-action" data-source="form_remove_external_links"><?=L( 'Remove external links' )?></button>
                        </div>
                      </div>
                    </form>
                    <form action="" method="post" id="form_remove_external_links">
                      <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                      <input type="hidden" name="action" value="external.links.remove">
                      <input type="hidden" name="urlID" value="<?=!empty( $metaData['rowid'] ) ? $metaData['rowid'] : ''?>"/>
                      <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                      <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
                    </form>
                  </div>
                </div>
              </div>
            </div>

            <?php if ( !inSafeMode() ) { ?>
              <div class="col mb-4">
                <div class="card h-100 shadow">
                  <div class="card-header text-white bg-dark">
                    <?=L( '.htaccess editor' )?>
                  </div>
                  <div class="card-body small">
                    <?=L( 'Be careful as invalid syntax would result in an internal server error.' )?>
                  </div>
                  <div class="card-footer text-right">
                    <form action="" method="post">
                      <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                      <input type="hidden" name="action" value="edit.htaccess">
                      <input type="hidden" name="urlID" value="<?=!empty( $metaData['rowid'] ) ? $metaData['rowid'] : ''?>"/>
                      <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                      <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
                      <button type="submit" class="btn btn-sm btn-primary"><?=L( 'Edit .htaccess' )?></button>
                    </form>
                  </div>
                </div>
              </div>
            <?php } ?>

            <?php $missingExtensions = getMissingExtensions( ['curl', 'zip'] ); ?>

            <div class="col mb-4">
              <div class="card h-100 shadow">
                <div class="card-header text-white bg-dark">Cloudflare</div>
                <div class="card-body small">
                  <?=L( 'Purge Cloudflare cache using API access.' )?>
                  <?php $acmsCloudflare = readMetaData( 'acms_cloudflare' );
                  if ( !empty( $acmsCloudflare ) ) {
                    $devModeTimeRemaining = !empty( $acmsCloudflare['data']['dev_mode_time_remaining'] ) ? $acmsCloudflare['data']['dev_mode_time_remaining'] - time() : 0;
                    ?>
                    <div class="alert-info p-3 my-3 rounded">
                      <?=L( 'Hostname' )?>: <?=$acmsCloudflare['data']['name']?><br/>
                      <?=L( 'Dev. Mode' )?>
                      : <?=( $devModeTimeRemaining > 0 ? getHumanTime( $devModeTimeRemaining ) : L( 'off' ) )?><br>
                      Zone ID: <?=$acmsCloudflare['data']['zone_id']?>
                    </div>
                    <div class="row">
                      <div class="col-xs-auto col-xl-6">
                        <form action="" method="post" class="mb-3">
                          <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                          <input type="hidden" name="action" value="api.cloudflare.purge.cache">
                          <button type="submit" class="btn btn-success btn-sm btn-block"><?=L( 'Purge cache' )?></button>
                        </form>
                      </div>
                      <div class="col-xs-auto col-xl-6">
                        <form action="" method="post" class="">
                          <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                          <input type="hidden" name="action" value="api.cloudflare.development.mode">
                          <input type="hidden" name="params[enable]" value="<?=( $devModeTimeRemaining > 0 ? 0 : 1 )?>">
                          <button type="submit" class="btn <?=( $devModeTimeRemaining > 0 ? 'btn-danger' : 'btn-success' )?> btn-sm btn-block text-truncate"><?=( $devModeTimeRemaining > 0 ? L( 'Turn OFF Dev Mode' ) : L( 'Turn ON Dev Mode' ) )?></button>
                        </form>
                      </div>
                    </div>
                  <?php } else { ?>
                    <form action="" method="post" class="needs-validation mt-3" novalidate>
                      <div class="form-group">
                        <label for="input_cf_zone_id">Zone ID</label>
                        <input type="text" class="form-control form-control-sm text-monospace" name="zone_id" id="input_cf_zone_id" pattern="[\w]{32}" required>
                      </div>
                      <div class="form-group">
                        <label for="input_cf_token">API token</label>
                        <div class="input-group mb-3">
                          <input type="text" class="form-control form-control-sm text-monospace" name="token" id="input_cf_token" pattern="[-\w]{40}" required>
                          <div class="input-group-append">
                            <button class="btn btn-primary btn-sm"><?=L( 'Save' )?></button>
                          </div>
                        </div>
                      </div>
                      <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                      <input type="hidden" name="action" value="api.cloudflare.create">
                    </form>
                  <?php } ?>
                </div>
                <div class="card-footer text-right">
                  <div class="float-left w-25 text-left">
                    <div class="logo-cloudflare" style="height:2em;"></div>
                  </div>
                  <?php if ( !empty( $acmsCloudflare ) ) { ?>
                    <form action="" method="post">
                      <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                      <input type="hidden" name="action" value="api.cloudflare.remove">
                      <button type="submit" class="btn btn-danger btn-sm"><?=L( 'Remove token' )?></button>
                    </form>
                  <?php } else { ?>
                    <a href="https://archivarix.com/<?=$_SESSION['archivarix.lang']?>/blog/cms/#cloudflare" target="_blank" class="btn btn-link btn-sm"><i class="fab fa-readme fa-fw"></i> <?=L( 'Read instructions' )?>
                    </a>
                  <?php } ?>
                </div>
              </div>
            </div>

            <?php if ( !inSafeMode() && !empty( $_SESSION['archivarix.expert'] ) ) { ?>
              <div class="col mb-4">
                <div class="card h-100 shadow">
                  <div class="card-header text-white bg-info">
                    <?=L( 'API management' )?>
                  </div>
                  <div class="card-body small">
                    <?=L( 'Reissue or remove API key that can be used for editing your website remotely.' )?>
                    <?php if ( isset( $uuidSettings['apikey'] ) ) { ?>
                      <div class="text-center mt-3">
                        <button class="btn btn-sm btn-dark text-nowrap" data-toggle="collapse" data-target="#apiKeyShow" aria-expanded="false" aria-controls="apiKeyShow">
                          <?=L( 'Show API key' )?>
                        </button>
                      </div>
                      <div class="collapse text-center p-3" id="apiKeyShow">
                        <div class="text-monospace"><?=$uuidSettings['apikey']?>
                          <i class="far fa-clipboard clipboard-copy" type="button" data-clipboard-text="<?=$uuidSettings['apikey']?>" aria-hidden="true"></i>
                        </div>
                        <span class="text-muted"><?=L( 'Do not share your API key with anyone.' )?></span>
                      </div>
                    <?php } ?>
                  </div>
                  <div class="card-footer text-right">
                    <?php if ( isset( $uuidSettings['apikey'] ) ) { ?>
                      <div class="btn-group">
                        <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><?=L( 'Reissue or remove' )?>
                        </button>
                        <div class="dropdown-menu">
                          <form action="" method="post">
                            <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                            <input type="hidden" name="action" value="api.key.generate">
                            <input type="hidden" name="urlID" value="<?=!empty( $metaData['rowid'] ) ? $metaData['rowid'] : ''?>"/>
                            <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                            <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
                            <button type="submit" class="dropdown-item text-primary">
                              <i class="fas fa-sync fa-fw"></i>
                              <?=L( 'Reissue API key' )?>
                            </button>
                          </form>
                          <form action="" method="post">
                            <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                            <input type="hidden" name="action" value="api.key.remove">
                            <input type="hidden" name="urlID" value="<?=!empty( $metaData['rowid'] ) ? $metaData['rowid'] : ''?>"/>
                            <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                            <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
                            <button type="submit" class="dropdown-item text-danger">
                              <i class="fas fa-trash-alt fa-fw"></i>
                              <?=L( 'Delete API key' )?>
                            </button>
                          </form>
                        </div>
                      </div>
                    <?php } else { ?>
                      <form action="" method="post">
                        <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                        <input type="hidden" name="action" value="api.key.generate">
                        <input type="hidden" name="urlID" value="<?=!empty( $metaData['rowid'] ) ? $metaData['rowid'] : ''?>"/>
                        <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                        <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
                        <button type="submit" class="btn btn-sm btn-primary">
                          <?=L( 'Generate API key' )?>
                        </button>
                      </form>
                    <?php } ?>
                  </div>
                </div>
              </div>
            <?php } ?>

            <?php if ( !inSafeMode() && !empty( $_SESSION['archivarix.expert'] ) ) { ?>
              <div class="col mb-4">
                <div class="card h-100 shadow">
                  <div class="card-header text-white bg-info">
                    <i class="fas fa-flask fa-fw"></i> <?=L( 'Flat-File version' )?>
                  </div>
                  <div class="card-body small">
                    <?=L( 'Export website to a flat-file structure' )?>
                  </div>
                  <div class="card-footer text-right">
                    <form action="" method="post">
                      <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                      <input type="hidden" name="action" value="fun.convert.website">
                      <input type="hidden" name="urlID" value="<?=!empty( $metaData['rowid'] ) ? $metaData['rowid'] : ''?>"/>
                      <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                      <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
                      <button type="submit" class="btn btn-sm btn-primary">
                        <?=L( 'Export' )?></button>
                    </form>
                  </div>
                </div>
              </div>
            <?php } ?>

            <?php if ( false && !inSafeMode() && !empty( $_SESSION['archivarix.expert'] ) ) { ?>
            <div class="col mb-4">
              <div class="card h-100 shadow">
                <form action="" method="post">
                  <div class="card-header text-white bg-info">
                    <i class="fas fa-flask fa-fw"></i> <?=L( 'Import' )?> MD
                  </div>
                  <div class="card-body small">
                    <div class="form-group">
                      <label><?=L( 'Filename' )?></label>
                      <input type="text" name="params[filename]" class="form-control">
                    </div>
                    <div class="form-group">
                      <label><?=L( 'Hostname' )?></label>
                      <input type="text" name="params[hostname]" class="form-control">
                    </div>
                  </div>
                  <div class="card-footer text-right">
                    <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                    <input type="hidden" name="action" value="fun.import.md">
                    <input type="hidden" name="urlID" value="<?=!empty( $metaData['rowid'] ) ? $metaData['rowid'] : ''?>"/>
                    <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                    <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
                    <button type="submit" class="btn btn-sm btn-primary">
                      <?=L( 'Import' )?></button>
                </form>
              </div>
            </div>
          </div>
        <?php } ?>

        </div>

        <?php }

        if ( $section == 'import' ) { ?>
          <div class="mb-3">
            <form action="" method="post" id="form_import_uuid" class="needs-validation" novalidate>
              <div class="input-group">
                <input type="text" class="form-control" name="uuid" pattern="[0-9a-zA-Z]{16}|[-0-9a-zA-Z]{19}" placeholder="<?=L( 'Enter serial number' )?>" required>
                <div class="input-group-append">
                  <button type="submit" class="btn btn-primary rounded-right"><?=L( 'Download' )?></button>
                </div>
                <div class="invalid-feedback"><?=L( 'Serial number has to be in a format of 16 characters XXXXXXXXXXXXXXXX or XXXX-XXXX-XXXX-XXXX' )?></div>
              </div>
              <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
              <input type="hidden" name="action" value="download.serial">
            </form>

            <div class="d-flex align-items-center text-secondary small text-uppercase">
              <div class="w-100">
                <hr class="my-0">
              </div>
              <div class="mx-2"><?=L( 'or' )?></div>
              <div class="w-100">
                <hr class="my-0">
              </div>
            </div>

            <form action="" method="post" id="form_import_upload" enctype="multipart/form-data" class="needs-validation" novalidate>
              <div class="input-group">
                <div class="custom-file">
                  <input type="file" class="custom-file-input input-upload-file" name="import_file" accept=".zip,application/zip,application/octet-stream,application/x-zip-compressed,multipart/x-zip" id="input_import_file" aria-describedby="button_import_file" required>
                  <label class="custom-file-label text-truncate" for="input_import_file"><?=L( 'Choose ZIP file' )?></label>
                </div>
                <div class="input-group-append">
                  <button class="btn btn-primary" type="submit" id="button_import_file"><?=L( 'Upload' )?></button>
                </div>
              </div>
              <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
              <input type="hidden" name="action" value="import.upload">
            </form>
          </div>

          <?php
          $imports = getImportsList();
          foreach ( $imports as $import ) { ?>
            <div class="bg-light rounded border shadow p-3 mb-3">
              <div class="row">
                <div class="col-12 col-md-3 mb-3">
                  <img src="<?=$import['screenshot']?>" class="img-fluid w-100 border rounded" onerror="this.src='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAIAAAABLCAIAAAA3cxjrAAAABnRSTlMAAAAAAABupgeRAAAAMklEQVR42u3BAQEAAACCIP+vbkhAAQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAEcGcMsAAQVgyaQAAAAASUVORK5CYII=';">
                </div>
                <div class="col-12 col-md-6 mb-3">
                  <div class="text-uppercase h5"><?=convertIdnToUtf8( $import['info']['settings']['domain'] )?>
                    <a href="<?=$import['url']?>" target="_blank"><i class="fas fa-external-link-alt fa-fw small"></i></a>
                  </div>
                  <div class="small"><?=$import['filename']?>
                    (<?=getHumanSize( $import['filesize'] );?>)
                  </div>
                  <div class="small text-muted">
                    <?=sprintf( L( 'Contains: %d files, %s of data' ), $import['info']['filescount'], getHumanSize( $import['info']['filessize'] ) )?>
                  </div>
                </div>
                <div class="col-12 col-md-3">
                  <button class="btn btn-block btn-success mb-3" data-toggle="collapse" data-target="#div_form_import_<?=$import['id']?>" aria-expanded="false" aria-controls="div_form_import_<?=$import['id']?>">
                    <i class="fas fa-cog fa-fw"></i> <?=L( 'Import' )?></button>
                  <button class="btn btn-block btn-primary mb-3" data-toggle="collapse" data-target="#div_import_stats_<?=$import['id']?>" aria-expanded="false" aria-controls="div_import_stats_<?=$import['id']?>">
                    <i class="fas fa-chart-pie fa-fw"></i> <?=L( 'Stats' )?>
                  </button>
                  <form action="" method="post">
                    <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                    <input type="hidden" name="action" value="import.remove">
                    <input type="hidden" name="filename" value="<?=htmlspecialchars( $import['filename'] )?>">
                    <button class="btn btn-block btn-danger" type="submit">
                      <i class="fas fa-trash-alt fa-fw"></i> <?=L( 'Delete' )?></button>
                  </form>
                </div>
              </div>

              <div class="py-3 collapse" id="div_form_import_<?=$import['id']?>">
                <form action="" method="post" id="form_import_<?=$import['id']?>">

                  <div class="mb-3">
                    <table class="table table-striped table-sm table-responsive">
                      <thead>
                      <tr>
                        <td class="w-100">
                          <div class="form-check font-weight-bold">
                            <input class="form-check-input check-all" data-group="check-<?=$import['id']?>" type="checkbox" checked id="check_all_<?=$import['id']?>"><label class="form-check-label" for="check_all_<?=$import['id']?>"><?=L( 'Hostnames' )?></label>
                          </div>
                        </td>
                        <th><?=L( 'Files' )?></th>
                      </tr>
                      </thead>
                      <?php foreach ( $import['info']['hostnames'] as $hostname ) { ?>
                        <tr>
                          <td>
                            <div class="form-check">
                              <input class="form-check-input check-<?=$import['id']?>" type="checkbox" name="settings[hostnames][]" value="<?=$hostname['hostname']?>" checked id="check_<?=$hostname['hostname']?>_<?=$import['id']?>"><label class="form-check-label" for="check_<?=$hostname['hostname']?>_<?=$import['id']?>"><?=convertIdnToUtf8( $hostname['hostname'] )?></label>
                            </div>
                          </td>
                          <td><?=$hostname['count']?></td>
                        </tr>
                      <?php } ?>
                    </table>
                  </div>

                  <?php if ( $import['acms_settings'] && !inSafeMode() ) { ?>
                    <div class="form-group form-check mb-0">
                      <input type="hidden" name="settings[acms_settings]" value="0">
                      <input class="form-check-input" type="checkbox" name="settings[acms_settings]" value="1" id="check_acms_settings_<?=$import['id']?>" checked>
                      <label class="form-check-label" for="check_acms_settings_<?=$import['id']?>"><?=L( 'Import Archivarix CMS settings' )?></label>
                    </div>
                  <?php } ?>

                  <?php if ( $import['loader_settings'] && !inSafeMode() ) { ?>
                    <div class="form-group form-check mb-0">
                      <input type="hidden" name="settings[loader_settings]" value="0">
                      <input class="form-check-input" type="checkbox" name="settings[loader_settings]" value="1" id="check_loader_settings_<?=$import['id']?>" checked>
                      <label class="form-check-label" for="check_loader_settings_<?=$import['id']?>"><?=L( 'Import Archivarix Loader settings' )?></label>
                    </div>
                  <?php } ?>

                  <?php if ( !empty( $import['templates'] ) ) { ?>
                    <div class="form-group form-check mb-0">
                      <input type="hidden" name="settings[templates]" value="0">
                      <input class="form-check-input" type="checkbox" name="settings[templates]" value="1" id="check_templates_<?=$import['id']?>" checked>
                      <label class="form-check-label" for="check_templates_<?=$import['id']?>"><?=sprintf( L( 'Import detected templates (%d)' ), count( $import['templates'] ) )?>
                        (<a href="" data-toggle="collapse" data-target="#div_templates_<?=$import['id']?>" aria-expanded="false" aria-controls="div_templates_<?=$import['id']?>"><?=L( 'show' )?></a>)</label>
                      <div class="collapse list-group mt-2" id="div_templates_<?=$import['id']?>">
                        <li class="list-group-item list-group-item-action list-group-item-secondary h6 m-0 rounded-0"><?=L( 'Templates' )?></li>
                        <?php foreach ( $import['templates'] as $importTemplate ) { ?>
                          <li class="list-group-item list-group-item-action py-1 text-nowrap text-truncate text-monospace rounded-0">
                            <i class="far fa-file-code fa-fw"></i> <?=$importTemplate['name']?>
                          </li>
                        <?php } ?>
                      </div>
                    </div>
                  <?php } ?>

                  <?php if ( !empty( $import['custom_includes'] ) && !inSafeMode() ) { ?>
                    <div class="form-group form-check mb-0">
                      <input type="hidden" name="settings[custom_includes]" value="0">
                      <input class="form-check-input" type="checkbox" name="settings[custom_includes]" value="1" id="check_custom_includes_settings_<?=$import['id']?>">
                      <label class="form-check-label" for="check_custom_includes_settings_<?=$import['id']?>"><?=L( 'Import files from custom \'includes\' directory' )?>
                        (<a href="" data-toggle="collapse" data-target="#div_custom_includes_<?=$import['id']?>" aria-expanded="false" aria-controls="div_custom_includes_<?=$import['id']?>"><?=L( 'show files' )?></a>)</label>
                      <div class="collapse list-group mt-2" id="div_custom_includes_<?=$import['id']?>">
                        <li class="list-group-item list-group-item-action list-group-item-secondary h6 m-0 rounded-0"><?=L( 'Custom Files' )?></li>
                        <?php foreach ( $import['custom_includes'] as $customInclude ) { ?>
                          <li class="list-group-item list-group-item-action px-0 py-1 text-nowrap text-truncate text-monospace rounded-0">
                            <?=str_repeat( '<i class="fas fa-fw"></i>', $customInclude['levels'] )?>
                            <?=( $customInclude['is_dir'] ? '<i class="far fa-fw fa-folder"></i>' : '<i class="fas fa-fw"></i><i class="far fa-fw fa-file-alt"></i>' )?>
                            <?=basename( $customInclude['filename'] )?>
                            <span class="text-muted"><?=( $customInclude['is_dir'] ? '' : '(' . getHumanSize( $customInclude['size'], 0 ) . ')' )?></span>
                          </li>
                        <?php } ?>
                      </div>
                      <div class="small text-danger mt-1">
                        <i class="fas fa-exclamation-triangle fa-fw"></i> <?=L( 'Attention! Any file inside \'includes\' directory can have executable php source code. Do not import files from untrusted sources.' )?>
                      </div>
                    </div>
                  <?php } ?>

                  <div class="form-check mt-3">
                    <input class="form-check-input" type="radio" name="settings[overwrite]" value="newer" id="option_overwrite_newer_<?=$hostname['hostname']?>_<?=$import['id']?>" checked>
                    <label class="form-check-label" for="option_overwrite_newer_<?=$hostname['hostname']?>_<?=$import['id']?>"><?=L( 'Overwrite existing URLs only of imported version is newer' )?></label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="settings[overwrite]" value="all" id="option_overwrite_all_<?=$hostname['hostname']?>_<?=$import['id']?>">
                    <label class="form-check-label" for="option_overwrite_all_<?=$hostname['hostname']?>_<?=$import['id']?>"><?=L( 'Overwrite all urls' )?></label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="settings[overwrite]" value="skip" id="option_overwrite_skip_<?=$hostname['hostname']?>_<?=$import['id']?>">
                    <label class="form-check-label" for="option_overwrite_skip_<?=$hostname['hostname']?>_<?=$import['id']?>"><?=L( 'Do not overwrite existing urls' )?></label>
                  </div>

                  <div class="form-group form-check mt-3">
                    <input type="hidden" name="settings[submerge]" value="0">
                    <input class="form-check-input" type="checkbox" name="settings[submerge]" value="1" id="check_submerge_<?=$import['id']?>">
                    <label class="form-check-label" for="check_submerge_<?=$import['id']?>"><?=L( 'Merge all URLs from subdomains to the main domain' )?></label>
                    <small class="form-text text-muted"><?=L( 'Newer version of URL has priority' )?></small>
                  </div>

                  <div class="form-group">
                    <label for="input_subdomain_<?=$import['id']?>"><?=L( 'Import everything to a subdomain' )?></label>
                    <div class="input-group">
                      <input type="text" class="form-control text-right" name="settings[subdomain]" placeholder="<?=L( 'subdomain' )?>" id="input_subdomain_<?=$import['id']?>">
                      <div class="input-group-append">
                        <span class="input-group-text">.<?=convertIdnToUtf8( $uuidSettings['domain'] )?></span>
                      </div>
                    </div>
                    <small class="form-text text-muted"><?=L( 'Leave empty for a normal import.' )?></small>
                  </div>

                  <div class="text-right">
                    <button type="submit" class="btn btn-primary"><?=L( 'Start import' )?></button>
                  </div>
                  <input type="hidden" name="filename" value="<?=$import['filename']?>">
                  <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                  <input type="hidden" name="action" value="import.perform">
                </form>
              </div>

              <div class="py-3 collapse stats-collapse" id="div_import_stats_<?=$import['id']?>" data-id="<?=$import['info']['id']?>">
                <?php printStats( $import['info'] ) ?>
              </div>

            </div>
            <?php
          }
          ?>
        <?php }

        if ( $section == 'search' ) {
          if ( !extension_loaded( 'mbstring' ) ) { ?>
            <div class="rounded bg-danger text-light p-3 mb-3">
              <i class="fas fa-exclamation-triangle mr-2"></i><?=L( 'PHP Extension mbstring is missing. It is required for working with different charsets.' )?>
            </div>
          <?php } ?>

          <ul class="nav nav-pills nav-justified mb-3" id="searchTab" role="tablist">
            <li class="nav-item">
              <a class="nav-link text-uppercase <?=$_POST['action'] == 'searchreplace.code' ? 'active' : ''?>" id="searchCodeTab" data-toggle="tab" href="#searchCode" role="tab" aria-controls="searchCode" aria-selected="<?=$_POST['action'] == 'searchreplace.code' ? 'true' : 'false'?>"><?=L( 'Code / Text' )?></a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?=$_POST['action'] == 'searchreplace.url' ? 'active' : ''?>" id="searchUrlsTab" data-toggle="tab" href="#searchUrls" role="tab" aria-controls="searchUrls" aria-selected="<?=$_POST['action'] == 'searchreplace.url' ? 'true' : 'false'?>"><?=L( 'URLs' )?></a>
            </li>
          </ul>

          <div class="tab-content" id="searchTab">
            <div class="tab-pane fade <?=$_POST['action'] == 'searchreplace.code' ? 'show active' : ''?>" id="searchCode" role="tabpanel" aria-labelledby="searchCodeTab">
              <div class="mb-3 rounded p-3 bg-dark text-light border">
                <form action="" method="post" class="needs-validation" novalidate>
                  <div class="form-group">
                    <label><?=L( 'Search for code/text' )?></label>
                    <textarea class="form-control text-monospace" name="search" required><?=htmlspecialchars( $_POST['search'] )?></textarea>
                  </div>
                  <div class="form-group">
                    <label><?=L( 'Replace with' )?></label>
                    <textarea class="form-control text-monospace" name="replace"><?=htmlspecialchars( $_POST['replace'] )?></textarea>
                  </div>


                  <div class="custom-control custom-checkbox">
                    <input type="hidden" name="regex" value="0"/>
                    <input type="checkbox" class="custom-control-input" id="perlRegex" name="regex" value="1" <?=( !empty( $_POST['regex'] ) ? 'checked' : '' )?>>
                    <label class="custom-control-label" for="perlRegex"><?=L( 'Regular expression' )?></label>
                    <a href="#" class="text-light small" data-toggle="modal" data-target="#regexModal"><i class="fas fa-info-circle"></i></a>
                  </div>
                  <div class="custom-control custom-checkbox">
                    <input type="hidden" name="text_files_search" value="0"/>
                    <input type="checkbox" class="custom-control-input" id="textFilesSearch" name="text_files_search" value="1" <?=( !empty( $_POST['text_files_search'] ) ? 'checked' : '' )?>>
                    <label class="custom-control-label" for="textFilesSearch"><?=L( 'Including text files (js, css, txt, json, xml)' )?></label>
                  </div>
                  <div class="mt-3">
                    <div class="search-filter-wrapper search-code-filter-wrapper">
                      <?php if ( !empty( $_POST['filter']['text'] ) ) {
                        foreach ( $_POST['filter']['param'] as $filterNum => $filterVal ) {
                          if ( !strlen( $_POST['filter']['text'][$filterNum] ) ) continue;
                          $filterCur = [
                            'param'    => $_POST['filter']['param'][$filterNum],
                            'operator' => $_POST['filter']['operator'][$filterNum],
                            'text'     => $_POST['filter']['text'][$filterNum],
                            'regex'    => $_POST['filter']['regex'][$filterNum],
                          ];
                          ?>
                          <div class="search-filter-block rounded-bottom bg-light text-dark p-0 small mb-3 position-relative ">
                            <div class="position-absolute" style="top:-10px; right:-10px;">
                              <span class="fa-stack remove-search-filter bg-transparent" type="button">
                                <i class="fas fa-circle fa-stack-2x fa-fw text-danger"></i>
                                <i class="fas fa-trash-alt fa-stack-1x fa-fw fa-inverse"></i>
                              </span>
                            </div>
                            <hr class="bg-success p-1 m-0">
                            <div class="p-3">
                              <div class="form-row">
                                <div class="col-12 col-sm-6">
                                  <select name="filter[param][]" class="form-control mb-2 search-filter-param">
                                    <option data-tag="textarea" data-type="" data-regex="1" data-operators="contains,contains-not" data-placeholder="" value="code" <?=( $filterCur['param'] == 'code' ? 'selected' : '' )?>><?=L( 'Code / Text' )?></option>
                                    <option data-tag="input" data-type="text" data-regex="1" data-operators="contains,contains-not" data-placeholder="" value="url" <?=( $filterCur['param'] == 'url' ? 'selected' : '' )?>><?=L( 'URL' )?></option>
                                    <option data-tag="input" data-type="text" data-regex="1" data-operators="contains,contains-not" data-placeholder="" value="hostname" <?=( $filterCur['param'] == 'hostname' ? 'selected' : '' )?>><?=L( 'Hostname' )?></option>
                                    <option data-tag="input" data-type="text" data-regex="1" data-operators="contains,contains-not" data-placeholder="" value="mime" <?=( $filterCur['param'] == 'mime' ? 'selected' : '' )?>><?=L( 'MIME-type' )?></option>
                                    <option data-tag="input" data-type="number" data-regex="0" data-operators="from,to" data-placeholder="<?=L( 'YYYYMMDDHHMMSS' )?>" value="datetime" <?=( $filterCur['param'] == 'datetime' ? 'selected' : '' )?>><?=L( 'Date/time' )?></option>
                                    <option data-tag="input" data-type="text" data-regex="0" data-operators="from,to" data-placeholder="<?=L( 'size in bytes' )?>" value="filesize" <?=( $filterCur['param'] == 'filesize' ? 'selected' : '' )?>><?=L( 'File size' )?></option>
                                  </select>
                                </div>
                                <div class="col-12 col-sm-6">
                                  <select name="filter[operator][]" class="form-control mb-2 search-filter-operator">
                                    <option value="contains" <?=( $filterCur['operator'] == 'contains' ? 'selected' : '' )?> <?=( in_array( $filterCur['param'], ['code', 'url', 'mime', 'hostname'] ) ? '' : 'disabled hidden' )?>><?=L( 'Contains' )?></option>
                                    <option value="contains-not" <?=( $filterCur['operator'] == 'contains-not' ? 'selected' : '' )?> <?=( in_array( $filterCur['param'], ['code', 'url', 'mime', 'hostname'] ) ? '' : 'disabled hidden' )?>><?=L( 'Does not contain' )?></option>
                                    <option value="from" <?=( $filterCur['operator'] == 'from' ? 'selected' : '' )?> <?=( in_array( $filterCur['param'], ['datetime', 'filesize'] ) ? '' : 'disabled hidden' )?>><?=L( 'From' )?></option>
                                    <option value="to" <?=( $filterCur['operator'] == 'to' ? 'selected' : '' )?> <?=( in_array( $filterCur['param'], ['datetime', 'filesize'] ) ? '' : 'disabled hidden' )?>><?=L( 'To' )?></option>
                                  </select>
                                </div>
                                <div class="col-12">
                                  <div class="form-group mb-2">
                                    <?php if ( in_array( $filterCur['param'], ['code'] ) ) { ?>
                                      <textarea name="filter[text][]" class="form-control search-filter-text text-monospace" placeholder=""><?=htmlspecialchars( $filterCur['text'] )?></textarea>
                                    <?php } elseif ( in_array( $filterCur['param'], ['url', 'mime', 'hostname'] ) ) { ?>
                                      <input type="text" name="filter[text][]" class="form-control search-filter-text text-monospace" placeholder="" value="<?=htmlspecialchars( $filterCur['text'] )?>"/>
                                    <?php } elseif ( in_array( $filterCur['param'], ['datetime'] ) ) { ?>
                                      <input type="number" name="filter[text][]" class="form-control search-filter-text text-monospace" placeholder="<?=L( 'YYYYMMDDHHMMSS' )?>" value="<?=htmlspecialchars( $filterCur['text'] )?>"/>
                                    <?php } elseif ( in_array( $filterCur['param'], ['filesize'] ) ) { ?>
                                      <input type="text" name="filter[text][]" class="form-control search-filter-text text-monospace" placeholder="<?=L( 'size in bytes' )?>" value="<?=htmlspecialchars( $filterCur['text'] )?>"/>
                                    <?php } ?>
                                  </div>
                                </div>
                                <div class="col-12 search-filter-regex-div <?=( in_array( $filterCur['param'], ['datetime', 'filesize'] ) ? 'd-none' : '' )?>">
                                  <div class="form-check" style="line-height:1 !important;">
                                    <label class="form-check-label">
                                      <input type="hidden" class="search-filter-regex" name="filter[regex][]" value="<?=$filterCur['regex']?>"/>
                                      <input type="checkbox" class="form-check-input search-filter-regex-checkbox" style="margin-top: 0 !important;" <?=( $filterCur['regex'] ? 'checked' : '' )?>>
                                      <?=L( 'Regular expression' )?></label>
                                  </div>
                                </div>
                              </div>
                            </div>
                          </div>
                        <?php }
                      } ?>
                    </div>

                    <button type="button" class="btn btn-success btn-sm mb-3 search-code-add-filter">
                      <i class="fas fa-plus-square fa-fw"></i> <?=L( 'Filter rule' )?></button>

                  </div>

                  <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                  <input type="hidden" name="action" value="searchreplace.code"/>
                  <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                  <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>

                  <div class="text-center">
                    <button type="submit" class="btn btn-primary m-1" name="type" value="search"><?=L( 'Search only' )?></button>
                    <button type="submit" class="btn btn-warning m-1" name="type" value="replace"><?=L( 'Search & Replace' )?></button>
                  </div>
                </form>
              </div>
            </div>

            <div class="tab-pane fade <?=$_POST['action'] == 'searchreplace.url' ? 'show active' : ''?>" id="searchUrls" role="tabpanel" aria-labelledby="searchUrlsTab">
              <div class="mb-3 rounded p-3 bg-search-url text-dark border">
                <form action="" method="post" class="needs-validation" novalidate>
                  <div class="form-group">
                    <label><?=L( 'Search in URL for' )?></label>
                    <textarea class="form-control text-monospace" name="search" required><?=htmlspecialchars( $_POST['search'] )?></textarea>
                  </div>
                  <div class="form-group">
                    <label><?=L( 'Replace with' )?></label>
                    <textarea class="form-control text-monospace" name="replace"><?=htmlspecialchars( $_POST['replace'] )?></textarea>
                  </div>

                  <div class="custom-control custom-checkbox">
                    <input type="hidden" name="regex" value="0"/>
                    <input type="checkbox" class="custom-control-input" id="perlRegexUrl" name="regex" value="1" <?=( !empty( $_POST['regex'] ) ? 'checked' : '' )?>>
                    <label class="custom-control-label" for="perlRegexUrl"><?=L( 'Regular expression' )?></label>
                    <a href="#" class="text-dark small" data-toggle="modal" data-target="#regexModal"><i class="fas fa-info-circle"></i></a>
                  </div>
                  <div class="custom-control custom-checkbox">
                    <input type="hidden" name="text_files_search" value="0"/>
                    <input type="checkbox" class="custom-control-input" id="replaceUrl" name="replaceUrl" value="1" <?=( !empty( $_POST['replaceUrl'] ) ? 'checked' : '' )?>>
                    <label class="custom-control-label" for="replaceUrl"><?=L( 'Replace if the same URL already exists and replace version is newer' )?></label>
                  </div>
                  <div class="mt-3">
                    <div class="search-filter-wrapper search-url-filter-wrapper">
                      <?php if ( !empty( $_POST['filter']['text'] ) ) {
                        foreach ( $_POST['filter']['param'] as $filterNum => $filterVal ) {
                          if ( !strlen( $_POST['filter']['text'][$filterNum] ) ) continue;
                          $filterCur = [
                            'param'    => $_POST['filter']['param'][$filterNum],
                            'operator' => $_POST['filter']['operator'][$filterNum],
                            'text'     => $_POST['filter']['text'][$filterNum],
                            'regex'    => $_POST['filter']['regex'][$filterNum],
                          ];
                          ?>
                          <div class="search-filter-block rounded-bottom bg-light text-dark p-0 small mb-3 position-relative ">
                            <div class="position-absolute" style="top:-10px; right:-10px;">
                              <span class="fa-stack remove-search-filter bg-transparent" type="button">
                                <i class="fas fa-circle fa-stack-2x fa-fw text-danger"></i>
                                <i class="fas fa-trash-alt fa-stack-1x fa-fw fa-inverse"></i>
                              </span>
                            </div>
                            <hr class="bg-success p-1 m-0">
                            <div class="p-3">
                              <div class="form-row">
                                <div class="col-12 col-sm-6">
                                  <select name="filter[param][]" class="form-control mb-2 search-filter-param">
                                    <option data-tag="textarea" data-type="" data-regex="1" data-operators="contains,contains-not" data-placeholder="" value="code" <?=( $filterCur['param'] == 'code' ? 'selected' : '' )?>><?=L( 'Code / Text' )?></option>
                                    <option data-tag="input" data-type="text" data-regex="1" data-operators="contains,contains-not" data-placeholder="" value="url" <?=( $filterCur['param'] == 'url' ? 'selected' : '' )?>><?=L( 'URL' )?></option>
                                    <option data-tag="input" data-type="text" data-regex="1" data-operators="contains,contains-not" data-placeholder="" value="hostname" <?=( $filterCur['param'] == 'hostname' ? 'selected' : '' )?>><?=L( 'Hostname' )?></option>
                                    <option data-tag="input" data-type="text" data-regex="1" data-operators="contains,contains-not" data-placeholder="" value="mime" <?=( $filterCur['param'] == 'mime' ? 'selected' : '' )?>><?=L( 'MIME-type' )?></option>
                                    <option data-tag="input" data-type="number" data-regex="0" data-operators="from,to" data-placeholder="<?=L( 'YYYYMMDDHHMMSS' )?>" value="datetime" <?=( $filterCur['param'] == 'datetime' ? 'selected' : '' )?>><?=L( 'Date/time' )?></option>
                                    <option data-tag="input" data-type="text" data-regex="0" data-operators="from,to" data-placeholder="<?=L( 'size in bytes' )?>" value="filesize" <?=( $filterCur['param'] == 'filesize' ? 'selected' : '' )?>><?=L( 'File size' )?></option>
                                  </select>
                                </div>
                                <div class="col-12 col-sm-6">
                                  <select name="filter[operator][]" class="form-control mb-2 search-filter-operator">
                                    <option value="contains" <?=( $filterCur['operator'] == 'contains' ? 'selected' : '' )?> <?=( in_array( $filterCur['param'], ['code', 'url', 'mime', 'hostname'] ) ? '' : 'disabled hidden' )?>><?=L( 'Contains' )?></option>
                                    <option value="contains-not" <?=( $filterCur['operator'] == 'contains-not' ? 'selected' : '' )?> <?=( in_array( $filterCur['param'], ['code', 'url', 'mime', 'hostname'] ) ? '' : 'disabled hidden' )?>><?=L( 'Does not contain' )?></option>
                                    <option value="from" <?=( $filterCur['operator'] == 'from' ? 'selected' : '' )?> <?=( in_array( $filterCur['param'], ['datetime', 'filesize'] ) ? '' : 'disabled hidden' )?>><?=L( 'From' )?></option>
                                    <option value="to" <?=( $filterCur['operator'] == 'to' ? 'selected' : '' )?> <?=( in_array( $filterCur['param'], ['datetime', 'filesize'] ) ? '' : 'disabled hidden' )?>><?=L( 'To' )?></option>
                                  </select>
                                </div>
                                <div class="col-12">
                                  <div class="form-group mb-2">
                                    <?php if ( in_array( $filterCur['param'], ['code'] ) ) { ?>
                                      <textarea name="filter[text][]" class="form-control search-filter-text text-monospace" placeholder=""><?=htmlspecialchars( $filterCur['text'] )?></textarea>
                                    <?php } elseif ( in_array( $filterCur['param'], ['url', 'mime', 'hostname'] ) ) { ?>
                                      <input type="text" name="filter[text][]" class="form-control search-filter-text text-monospace" placeholder="" value="<?=htmlspecialchars( $filterCur['text'] )?>"/>
                                    <?php } elseif ( in_array( $filterCur['param'], ['datetime'] ) ) { ?>
                                      <input type="number" name="filter[text][]" class="form-control search-filter-text text-monospace" placeholder="<?=L( 'YYYYMMDDHHMMSS' )?>" value="<?=htmlspecialchars( $filterCur['text'] )?>"/>
                                    <?php } elseif ( in_array( $filterCur['param'], ['filesize'] ) ) { ?>
                                      <input type="text" name="filter[text][]" class="form-control search-filter-text text-monospace" placeholder="<?=L( 'size in bytes' )?>" value="<?=htmlspecialchars( $filterCur['text'] )?>"/>
                                    <?php } ?>
                                  </div>
                                </div>
                                <div class="col-12 search-filter-regex-div <?=( in_array( $filterCur['param'], ['datetime', 'filesize'] ) ? 'd-none' : '' )?>">
                                  <div class="form-check" style="line-height:1 !important;">
                                    <label class="form-check-label">
                                      <input type="hidden" class="search-filter-regex" name="filter[regex][]" value="<?=$filterCur['regex']?>"/>
                                      <input type="checkbox" class="form-check-input search-filter-regex-checkbox" style="margin-top: 0 !important;" <?=( $filterCur['regex'] ? 'checked' : '' )?>>
                                      <?=L( 'Regular expression' )?></label>
                                  </div>
                                </div>
                              </div>
                            </div>
                          </div>
                        <?php }
                      } ?>
                    </div>

                    <button type="button" class="btn btn-success btn-sm mb-3 search-url-add-filter">
                      <i class="fas fa-plus-square fa-fw"></i> <?=L( 'Filter rule' )?></button>

                  </div>
                  <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                  <input type="hidden" name="action" value="searchreplace.url"/>
                  <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                  <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>

                  <div class="text-center">
                    <button type="submit" class="btn btn-primary m-1" name="type" value="search"><?=L( 'Search only' )?></button>
                    <button type="submit" class="btn btn-warning m-1" name="type" value="replace"><?=L( 'Search & Replace' )?></button>
                  </div>
                </form>
              </div>
            </div>


            <div class="search-filter-block d-none rounded-bottom bg-light text-dark p-0 small mb-3 position-relative ">
              <div class="position-absolute" style="top:-10px; right:-10px;">
                  <span class="fa-stack remove-search-filter bg-transparent" type="button">
                    <i class="fas fa-circle fa-stack-2x fa-fw text-danger"></i>
                    <i class="fas fa-trash-alt fa-stack-1x fa-fw fa-inverse"></i>
                  </span>
              </div>
              <hr class="bg-secondary p-1 m-0">
              <div class="p-3">
                <div class="form-row">
                  <div class="col-12 col-sm-6">
                    <select name="filter[param][]" class="form-control mb-2 search-filter-param">
                      <option data-tag="textarea" data-type="" data-regex="1" data-operators="contains,contains-not" data-placeholder="" value="code"><?=L( 'Code / Text' )?></option>
                      <option data-tag="input" data-type="text" data-regex="1" data-operators="contains,contains-not" data-placeholder="" value="url"><?=L( 'URL' )?></option>
                      <option data-tag="input" data-type="text" data-regex="1" data-operators="contains,contains-not" data-placeholder="" value="hostname"><?=L( 'Hostname' )?></option>
                      <option data-tag="input" data-type="text" data-regex="1" data-operators="contains,contains-not" data-placeholder="" value="mime"><?=L( 'MIME-type' )?></option>
                      <option data-tag="input" data-type="number" data-regex="0" data-operators="from,to" data-placeholder="<?=L( 'YYYYMMDDHHMMSS' )?>" value="datetime"><?=L( 'Date/time' )?></option>
                      <option data-tag="input" data-type="text" data-regex="0" data-operators="from,to" data-placeholder="<?=L( 'size in bytes' )?>" value="filesize"><?=L( 'File size' )?></option>
                    </select>
                  </div>
                  <div class="col-12 col-sm-6">
                    <select name="filter[operator][]" class="form-control mb-2 search-filter-operator">
                      <option value="contains"><?=L( 'Contains' )?></option>
                      <option value="contains-not"><?=L( 'Does not contain' )?></option>
                      <option value="from" disabled hidden><?=L( 'From' )?></option>
                      <option value="to" disabled hidden><?=L( 'To' )?></option>
                    </select>
                  </div>
                  <div class="col-12">
                    <div class="form-group mb-2">
                      <textarea name="filter[text][]" class="form-control search-filter-text text-monospace" placeholder=""></textarea>
                    </div>
                  </div>
                  <div class="col-12 search-filter-regex-div">
                    <div class="form-check" style="line-height:1 !important;">
                      <label class="form-check-label">
                        <input type="hidden" class="search-filter-regex" name="filter[regex][]" value="0"/>
                        <input type="checkbox" class="form-check-input search-filter-regex-checkbox" style="margin-top: 0 !important;">
                        <?=L( 'Regular expression' )?></label>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <?php if ( count( $searchResults ) && $_POST['type'] == 'search' && !isset( $_POST['perform'] ) ) { ?>
            <div class="text-center mb-3">
              <form action="" method="post" id="form_confirm_remove">
                <input type="hidden" name="search" value="<?=htmlspecialchars( $_POST['search'] )?>"/>
                <input type="hidden" name="replace" value="<?=htmlspecialchars( $_POST['replace'] )?>"/>
                <input type="hidden" name="regex" value="<?=$_POST['regex']?>"/>
                <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                <input type="hidden" name="action" value="<?=$_POST['action']?>"/>
                <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
                <input type="hidden" name="type" value="<?=$_POST['type']?>"/>
                <input type="hidden" name="text_files_search" value="<?=$_POST['text_files_search']?>"/>
                <input type="hidden" name="perform" value="remove"/>
                <?php if ( !empty( $_POST['filter'] ) ) printFormFields( ['filter' => $_POST['filter']] ); ?>
                <?php if ( !empty( $_POST['replaceUrl'] ) ) { ?>
                  <input type="hidden" name="replaceUrl" value="<?=$_POST['replaceUrl']?>"/>
                <?php } ?>
                <button type="button" class="btn btn-danger btn-action" data-toggle="modal" data-target="#confirm-action" data-source="form_confirm_remove">
                  <i class="fas fa-trash-alt fa-fw"></i> <?=L( 'Remove all found URLs' )?>
                </button>
              </form>
            </div>
          <?php } ?>

          <?php if ( count( $searchResults ) && $_POST['type'] == 'replace' && !isset( $_POST['perform'] ) ) { ?>
            <div class="text-center mb-3">
              <form action="" method="post" id="form_confirm_replace">
                <input type="hidden" name="search" value="<?=htmlspecialchars( $_POST['search'] )?>"/>
                <input type="hidden" name="replace" value="<?=htmlspecialchars( $_POST['replace'] )?>"/>
                <input type="hidden" name="regex" value="<?=$_POST['regex']?>"/>
                <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                <input type="hidden" name="action" value="<?=$_POST['action']?>"/>
                <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
                <input type="hidden" name="type" value="<?=$_POST['type']?>"/>
                <input type="hidden" name="text_files_search" value="<?=$_POST['text_files_search']?>"/>
                <input type="hidden" name="perform" value="replace"/>
                <?php if ( !empty( $_POST['filter'] ) ) printFormFields( ['filter' => $_POST['filter']] ); ?>
                <?php if ( !empty( $_POST['replaceUrl'] ) ) { ?>
                  <input type="hidden" name="replaceUrl" value="<?=$_POST['replaceUrl']?>"/>
                <?php } ?>
                <button type="button" class="btn btn-success btn-action" data-toggle="modal" data-target="#confirm-action" data-source="form_confirm_replace">
                  <i class="fas fa-check fa-fw"></i> <?=L( 'Confirm all replaces' )?>
                </button>
              </form>
            </div>
          <?php } ?>
          <?php
          if ( !isset( $_POST['perform'] ) && count( $searchResults ) > 2 ) {
            $searchCount  = $searchResults['total_urls'];
            $totalMatches = $searchResults['total_matches'];
            $limitReached = $searchResults['limit_reached'];
            unset( $searchResults['total_urls'] );
            unset( $searchResults['total_matches'] );
            unset( $searchResults['limit_reached'] );
            ?>

            <div class="text-right small pb-3">
              <em>
                <?=sprintf( L( 'Pages found: %d; total matches: %d' ), $searchCount, $totalMatches )?>
              </em>
            </div>
            <?php if ( $limitReached ) { ?>
              <div class="bg-warning p-3 rounded border mb-3">
                <?=sprintf( L( 'Attention! Only %d matches are shown from %d due to matches display limit' ), $ACMS['ACMS_MATCHES_LIMIT'], $totalMatches )?>
              </div>
            <?php } ?>
          <?php } ?>

          <?php if ( !isset( $_POST['perform'] ) ) foreach ( $searchResults as $searchResult ) {
            if ( empty( $searchResult['results'] ) ) {
              continue;
            } ?>
            <div class="bg-light border rounded p-3 mb-2 search-result">
              <div class="row">
                <div class="col order-sm-1 order-2">
                  <div class="h5 text-monospace text-break">
                    <?=htmlspecialchars( rawurldecode( $searchResult['request_uri'] ), ENT_IGNORE )?>
                    <?=( !empty( $searchResult['replace_uri'] ) ? ' -> ' . htmlspecialchars( rawurldecode( $searchResult['replace_uri'] ), ENT_IGNORE ) : '' )?>
                    <?php if ( isset( $searchResult['valid_uri'] ) && !$searchResult['valid_uri'] ) { ?>
                      <div class="small text-danger"><?=L( 'Replace is not possible. Invalid new URL.' )?></div>
                    <?php } ?>
                    <div class="small"><?=convertIdnToUtf8( $searchResult['domain'] )?></div>
                  </div>
                </div>

                <div class="col-sm-auto col-12 text-center text-sm-right text-nowrap order-1 order-sm-2 mb-3 mb-sm-0">
                  <form action="" method="post" class="form-inline d-inline" id="url_remove_<?=$searchResult['rowid']?>" onsubmit="ajaxRemoveURL('url_remove_<?=$searchResult['rowid']?>'); return false;">
                    <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                    <input type="hidden" name="action" value="remove.url"/>
                    <input type="hidden" name="urlID" value="<?=$searchResult['rowid']?>"/>
                    <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                    <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
                    <input type="hidden" name="ajax" value="1"/>
                    <button type="submit" class="btn btn-sm btn-danger" title="<?=L( 'Remove URL' )?>">
                      <i class="fas fa-trash-alt fa-fw"></i></button>
                  </form>
                  <form target="_blank" action="" method="post" class="form-inline d-inline">
                    <input type="hidden" name="show" value="edit.url"/>
                    <input type="hidden" name="urlID" value="<?=$searchResult['rowid']?>"/>
                    <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                    <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
                    <button class="btn btn-sm btn-primary" title="<?=L( 'Edit page in external window' )?>">
                      <i class="fas fa-edit fa-fw"></i></button>
                  </form>
                  <a href="<?='//' . convertDomain( $searchResult['domain'] ) . $searchResult['request_uri']?>" target="_blank" class="btn btn-sm btn-primary" title="<?=L( 'Open URL in external window' )?>"><i class="fas fa-external-link-alt fa-fw"></i></a>
                </div>
              </div>
              <?php $n = 0;
              foreach ( $searchResult['results'] as $searchCode ) {
                $n++;
                if ( $searchResult['type'] == 'search' ) { ?>
                  <div class="mt-2 mb-0">
                    <span class="ml-2 px-2 py-0 text-light bg-success rounded-top text-monospace"><?=$n?></span>
                  </div>
                  <div class="bg-success text-light rounded p-2">
                    <div class="bg-success text-light rounded p-2">
                      <small>
                        <small class="float-right"><abbr title="<?=L( 'Position' )?>">@<?=$searchCode['position']?></abbr>
                        </small>
                      </small>
                      <pre class="m-0"><code class="text-white"><?=htmlspecialchars( ( $searchCode['result'] ) )?></code></pre>
                    </div>
                  </div>
                  <?php
                }
                if ( $searchResult['type'] == 'replace' ) {
                  ?>
                  <div class="mt-2 mb-0">
                    <span class="ml-2 px-2 py-0 text-light bg-warning rounded-top text-monospace"><?=$n?></span>
                  </div>
                  <div class="bg-success text-light rounded-top p-2">
                    <small>
                      <small class="float-right"><abbr title="<?=L( 'Position' )?>">@<?=$searchCode['position']?></abbr>
                      </small>
                    </small>
                    <pre class="m-0"><code class="text-white"><?=htmlspecialchars( ( $searchCode['original'] ) )?></code></pre>
                  </div>
                  <div class="bg-warning rounded-bottom mt-0 p-2">
                    <pre class="m-0"><code><?=htmlspecialchars( ( $searchCode['result'] ) )?></code></pre>
                  </div>

                  <?php
                }
              } ?>
            </div>
            <?php
          }
        }

        if ( !empty( $missingUrls ) ) { ?>
          <div class="border p-0 mb-3 rounded">
            <div class="row m-3">
              <div class="col-12 col-sm-auto p-1">
                <form class="d-inline" id="form_missing_urls_clear" action="" method="post">
                  <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                  <input type="hidden" name="action" value="missing.clear">
                  <input type="hidden" name="all" value="1">
                  <button type="submit" class="btn btn-danger m-1 btn-block">
                    <i class="fas fa-trash-alt fa-fw"></i> <?=L( 'Clear all' )?></button>
                </form>
              </div>
            </div>
          </div>
          <table data-toggle="table" data-pagination="true" data-search="true" data-show-columns="true" data-show-export="true" data-page-size="100" data-page-list="[10, 25, 50, 100, 200, All]" data-maintain-meta-data="true" data-locale="<?=( $_SESSION['archivarix.lang'] == 'en' ? 'en-US' : 'ru-RU' )?>" data-sort-class="bg-light" data-sort-name="status" data-sort-order="desc" data-trim-on-search="false" class="table table-bordered table-sm">
            <thead class="thead-light">
            <th data-field="wbm" data-sortable="false" data-visible="false" data-align="center"><?=L( 'WBM' )?></th>
            <th data-field="url" data-sortable="true" data-width="100" data-width-unit="%"><?=L( 'URL' )?></th>
            <th data-field="status" data-sortable="true"><?=L( 'Status' )?></th>
            <th data-field="ignored" data-sortable="true" data-visible="false"><?=L( 'Ignored' )?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ( $missingUrls as $missingUrl ) { ?>
              <tr data-unique-id="<?=$missingUrl['rowid']?>">
                <td data-search="false">
                  <a class="btn btn-sm btn-primary" target="_blank" rel="noreferrer noopener" href="https://web.archive.org/web/*/<?=$missingUrl['url']?>"><i class="fas fa-external-link-alt fa-fw"></i></a>
                </td>
                <td class="small"><?=htmlspecialchars( rawurldecode( $missingUrl['url'] ), ENT_IGNORE )?></td>
                <td class="text-center small"><?=$missingUrl['status']?></td>
                <td class="text-center small"><?=$missingUrl['ignore']?></td>
              </tr>
            <?php } ?>
            </tbody>
          </table>
          <?php
        }

        // EDIT URL
        if ( isset( $_POST['urlID'], $_POST['show'] ) && $_POST['show'] == 'edit.url' ) {
          $docBkps = getBackupsByDocumentId( $metaData['rowid'] );
          ?>
          <div class="border p-0 mb-3 rounded">
            <div class="row m-3">
              <div class="col-12 col-sm-auto p-1">
                <a href="<?=$metaData['protocol'] . '://' . convertDomain( $metaData['hostname'] ) . $metaData['request_uri']?>" class="btn btn-primary my-1 btn-block" target="_blank">
                  <i class="fas fa-external-link-alt fa-fw"></i> <?=L( 'Open URL' )?></a>
              </div>
              <div class="col-12 col-sm-auto p-1">
                <a href="#" class="btn btn-primary my-1 btn-block" data-toggle="modal" data-target="#cloneModal">
                  <i class="far fa-clone fa-fw"></i> <?=L( 'Clone URL' )?></a>
              </div>
              <div class="col-12 col-sm-auto p-1">
                <form action="" method="post" class="d-inline">
                  <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                  <input type="hidden" name="action" value="download.url"/>
                  <input type="hidden" name="show" value="edit.url"/>
                  <input type="hidden" name="urlID" value="<?=!empty( $metaData['rowid'] ) ? $metaData['rowid'] : ''?>"/>
                  <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                  <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
                  <button type="submit" class="btn btn-primary my-1 btn-block">
                    <i class="fas fa-download fa-fw"></i> <?=L( 'Download File' )?></button>
                </form>
              </div>
              <?php if ( true && $metaData['mimetype'] == 'text/html' ) { ?>
                <div class="col-12 col-sm-auto p-1">
                  <a href="#" class="btn btn-primary my-1 btn-block" data-toggle="modal" data-target="#templateModal">
                    <i class="fas fa-file-code fa-fw"></i> <?=L( 'Create template' )?></a>
                </div>
              <?php } ?>
              <div class="col-12 col-sm-auto p-1">
                <form action="" method="post" class="d-inline" id="url_remove_<?=$metaData['rowid']?>">
                  <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                  <input type="hidden" name="action" value="remove.url"/>
                  <input type="hidden" name="urlID" value="<?=!empty( $metaData['rowid'] ) ? $metaData['rowid'] : ''?>"/>
                  <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                  <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
                  <button type="button" class="btn btn-danger btn-action my-1 btn-block" data-toggle="modal" data-target="#confirm-action" data-source="url_remove_<?=$metaData['rowid']?>">
                    <i class="fas fa-trash-alt fa-fw"></i> <?=L( 'Remove URL' )?></button>
                </form>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-12 col-xl-10 mb-3">
              <div class="border rounded p-3">
                <form action="" method="post" class="needs-validation" novalidate>
                  <div class="row">
                    <div class="col-12 col-md-6">

                      <div class="form-group row">
                        <label class="col-sm-2 col-form-label col-form-label-sm text-nowrap"><?=L( 'URI address' )?></label>
                        <div class="col-sm-10">
                          <input type="text" class="form-control form-control-sm" name="request_uri" value="<?=htmlspecialchars( rawurldecode( $metaData['request_uri'] ), ENT_IGNORE )?>" required>
                        </div>
                      </div>

                      <div class="form-group row">
                        <label class="col-sm-2 col-form-label col-form-label-sm text-nowrap"><?=L( 'MIME-type' )?></label>
                        <div class="col-sm-10">
                          <input type="text" class="form-control form-control-sm" name="mimetype" pattern="[-a-z0-9+./]*" value="<?=htmlspecialchars( $metaData['mimetype'] )?>" required>
                        </div>
                      </div>

                      <div class="form-group row">
                        <label class="col-sm-2 col-form-label col-form-label-sm"><?=L( 'Charset' )?></label>
                        <div class="col-sm-10">
                          <input type="text" class="form-control form-control-sm" name="charset" pattern="[-a-z0-9]*" value="<?=htmlspecialchars( $metaData['charset'] )?>">
                        </div>
                      </div>

                    </div>

                    <div class="col-12 col-md-6">

                      <div class="form-group row">
                        <label class="col-sm-2 col-form-label col-form-label-sm"><?=L( 'Redirect' )?></label>
                        <div class="col-sm-10">
                          <input type="text" class="form-control form-control-sm" name="redirect" value="<?=htmlspecialchars( rawurldecode( $metaData['redirect'] ), ENT_IGNORE )?>">
                        </div>
                      </div>

                      <div class="form-group row">
                        <label class="col-sm-2 col-form-label col-form-label-sm"><?=L( 'Filetime' )?></label>
                        <div class="col-sm-10">
                          <input type="text" class="form-control form-control-sm" name="filetime" pattern="[0-9]+" value="<?=htmlspecialchars( $metaData['filetime'] )?>">
                        </div>
                      </div>

                      <div class="form-group row">
                        <label class="col-sm-2 col-form-label col-form-label-sm"><?=L( 'Enabled' )?></label>
                        <div class="col-sm-10">
                          <div class="form-group form-check mb-2">
                            <input type="hidden" name="enabled" value="0">
                            <input class="form-check-input form-control-sm" style="margin-top: inherit;" type="checkbox" name="enabled" value="1" <?=( $metaData['enabled'] ? 'checked' : '' )?> id="enabledCheck">
                            <label class="form-check-label col-form-label-sm" for="enabledCheck">
                              <?=L( 'Enable this URL' )?>
                            </label>
                          </div>
                        </div>
                      </div>

                    </div>

                  </div>

                  <div class="text-center text-lg-right">
                    <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                    <input type="hidden" name="action" value="update.url.settings"/>
                    <input type="hidden" name="show" value="edit.url"/>
                    <input type="hidden" name="urlID" value="<?=!empty( $metaData['rowid'] ) ? $metaData['rowid'] : ''?>"/>
                    <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                    <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
                    <button type="submit" class="btn btn-sm btn-primary">
                      <i class="fas fa-save fa-fw"></i> <?=L( 'Save settings only' )?></button>
                  </div>
                </form>
              </div>
            </div>
            <div class="col-12 col-xl-2 mb-3">
              <form action="<?=htmlspecialchars( $_SERVER['REQUEST_URI'] )?>" method="post" enctype="multipart/form-data" class="dropzone w-100 h-100" id="urlUpload">
                <div class="dz-message">
                  <i class="fas fa-file-upload fa-fw fa-2x"></i><br> <?=L( 'Drop file here to replace.' )?></div>
                <div class="fallback">
                  <input name="file" type="file" multiple/>
                </div>
                <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                <input type="hidden" name="action" value="update.url.upload"/>
                <input type="hidden" name="show" value="edit.url"/>
                <input type="hidden" name="urlID" value="<?=!empty( $metaData['rowid'] ) ? $metaData['rowid'] : ''?>"/>
                <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
              </form>
            </div>
          </div>
          <!-- PREVIEW AREA -->
          <?php if ( $metaData['redirect'] || !$metaData['enabled'] ) { ?>
            <div class="bg-warning p-3 rounded text-center">
              <?=L( 'Preview is not available because the URL is disabled or redirect is set.' )?>
            </div>
          <?php } ?>
          <?php
          if ( !$metaData['redirect'] && $metaData['enabled'] ) {
            $metaData['mime'] = getMimeInfo( $metaData['mimetype'] );
            switch ( $metaData['mime']['type'] ) {
              case 'html' :
              case 'text' :
              case 'code' :
                $isHtml = $metaData['mime']['type'] == 'html' ? true : false;
                ?>
                <ul class="nav nav-tabs mb-3" role="tablist" style="flex-wrap: inherit;">
                  <?php if ( $isHtml ) { ?>
                    <li class="nav-item">
                      <a class="nav-link active" id="wysiwyg-tab" data-toggle="tab" href="#wysiwyg" role="tab" aria-controls="wysiwyg" aria-selected="true"><?=L( 'WYSIWYG' )?></a>
                    </li>
                  <?php } ?>
                  <li class="nav-item">
                    <a class="nav-link <?=( !$isHtml ? ' active' : '' )?>" id="code-tab" data-toggle="tab" href="#code" role="tab" aria-controls="code" aria-selected="false"><?=L( 'Code' )?></a>
                  </li>
                  <?php if ( count( $docBkps ) ) { ?>
                    <li class="nav-item">
                      <a class="nav-link" id="backups-tab" data-toggle="tab" href="#backups" role="tab" aria-controls="backups" aria-selected="false"><?=L( 'History' )?></a>
                    </li>
                  <?php } ?>
                </ul>
                <div class="tab-content">
                  <?php if ( $isHtml ) { ?>
                    <div class="tab-pane fade show active" id="wysiwyg" role="tabpanel" aria-labelledby="wysiwyg-tab">
                      <form action="" method="post" onsubmit="ajaxSaveFile('formHtml'); return false;" id="formHtml">
                        <textarea id="textarea_html" name="content" class="w-100 h-100"><?=htmlspecialchars( convertEncoding( $content, 'UTF-8', $metaData['charset'] ) );?></textarea>
                        <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                        <input type="hidden" name="action" value="update.url.content"/>
                        <input type="hidden" name="show" value="edit.url"/>
                        <input type="hidden" name="urlID" value="<?=!empty( $metaData['rowid'] ) ? $metaData['rowid'] : ''?>"/>
                        <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                        <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
                        <input type="hidden" name="ajax" value="1"/>
                        <button type="submit" class="btn btn-primary float-right my-3">
                          <i class="fas fa-save fa-fw"></i> <?=L( 'Save' )?></button>
                      </form>
                    </div>
                  <?php } ?>
                  <div class="tab-pane fade <?=( !$isHtml ? ' show active' : '' )?>" id="code" role="tabpanel" aria-labelledby="code-tab">
                    <form action="" method="post" onsubmit="ajaxSaveFile('<?=( $isHtml ? 'formCode' : 'formText' )?>'); return false;" id="<?=( $isHtml ? 'formCode' : 'formText' )?>">
                      <textarea id="textarea_text" name="content"><?=htmlspecialchars( convertEncoding( $content, 'UTF-8', $metaData['charset'] ) );?></textarea>
                      <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                      <input type="hidden" name="action" value="update.url.content"/>
                      <input type="hidden" name="show" value="edit.url"/>
                      <input type="hidden" name="urlID" value="<?=!empty( $metaData['rowid'] ) ? $metaData['rowid'] : ''?>"/>
                      <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                      <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
                      <input type="hidden" name="ajax" value="1"/>
                      <button type="submit" class="btn btn-primary float-right mt-3" id="cmSave" style="display: block; position: fixed; bottom: 30px; right: 30px; z-index: 99;">
                        <i class="fas fa-save fa-fw"></i> <?=L( 'Save' )?></button>
                    </form>
                  </div>
                  <?php if ( count( $docBkps ) ) { ?>
                    <div class="tab-pane fade" id="backups" role="tabpanel" aria-labelledby="backups-tab">
                      <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="showUnmodified">
                        <label class="custom-control-label" for="showUnmodified"><?=L( 'Show unmodified lines' )?></label>
                      </div>
                      <?php
                      if ( count( $docBkps ) ) { ?>
                        <?php foreach ( $docBkps as $docBkpNum => $docBkp ) { ?>
                          <?php
                          if ( $docBkpNum == 0 ) {
                            $docBkpCurrentPath    = $sourcePath . DIRECTORY_SEPARATOR . $metaData['folder'] . DIRECTORY_SEPARATOR . $metaData['filename'];
                            $docBkpCurrentCharset = $metaData['charset'];
                            $docBkpCurrentMime    = $metaData['mimetype'];
                            $docBkpNextPath       = $sourcePath . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . $docBkp['filename'];
                            $docBkpNextCharset    = $docBkp['settings']['charset'];
                            $docBkpNextMime       = $docBkp['settings']['mimetype'];
                            if (
                              getMimeInfo( $docBkpCurrentMime )['folder'] != 'html' ||
                              getMimeInfo( $docBkpNextMime )['folder'] != 'html'
                            ) $docBkpChanges = '';
                            else $docBkpChanges = compareToHTML( compare(
                              convertEncoding( file_get_contents( $docBkpNextPath ), 'UTF-8', $docBkpNextCharset ),
                              convertEncoding( file_get_contents( $docBkpCurrentPath ), 'UTF-8', $docBkpCurrentCharset ),
                              false
                            ), '' );
                            ?>
                            <div class="d-flex align-items-center mt-3 mb-1">
                              <div class="mr-2 text-nowrap">
                                <span class="badge alert-success"><?=date( 'Y-m-d H:i:s', $docBkp['created'] )?></span>
                                <span class="badge alert-warning"><?=L( 'action' )?>: <?=$docBkp['action']?></span>
                              </div>
                              <div class="w-100">
                                <hr class="my-0">
                              </div>
                            </div>
                            <div class="revisions text-monospace small">
                              <?=$docBkpChanges?>
                            </div>
                            <?php
                          }
                          if ( key_exists( $docBkpNum + 1, $docBkps ) ) {
                            $docBkpCurrentPath    = $sourcePath . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . $docBkp['filename'];
                            $docBkpCurrentCharset = $docBkp['settings']['charset'];
                            $docBkpCurrentMime    = $docBkp['settings']['mimetype'];
                            $docBkpNextPath       = $sourcePath . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . $docBkps[$docBkpNum + 1]['filename'];
                            $docBkpNextCharset    = $docBkps[$docBkpNum + 1]['settings']['charset'];
                            $docBkpNextMime       = $docBkps[$docBkpNum + 1]['settings']['mimetype'];
                            if (
                              getMimeInfo( $docBkpCurrentMime )['folder'] != 'html' ||
                              getMimeInfo( $docBkpNextMime )['folder'] != 'html'
                            ) $docBkpChanges = '';
                            else $docBkpChanges = compareToHTML( compare(
                              convertEncoding( file_get_contents( $docBkpNextPath ), 'UTF-8', $docBkpNextCharset ),
                              convertEncoding( file_get_contents( $docBkpCurrentPath ), 'UTF-8', $docBkpCurrentCharset ),
                              false
                            ), '' );
                            ?>
                            <div class="d-flex align-items-center mt-3 mb-1">
                              <div class="mr-2 text-nowrap">
                                <span class="badge alert-success"><?=date( 'Y-m-d H:i:s', $docBkps[$docBkpNum + 1]['created'] )?></span>
                                <span class="badge alert-warning"><?=L( 'action' )?>: <?=$docBkps[$docBkpNum + 1]['action']?></span>
                              </div>
                              <div class="w-100">
                                <hr class="my-0">
                              </div>
                            </div>
                            <div class="revisions text-monospace small">
                              <?=$docBkpChanges?>
                            </div>
                            <?php
                          }
                          ?>
                        <?php }
                      }
                      ?>
                    </div>
                  <?php } ?>
                </div>
                <?php
                break;

              case 'video' :
                ?>
                <div class="text-center p-3 mb-3">
                  <video src="<?=$metaData['request_uri']?>" type="<?=$metaData['mimetype']?>" controls>
                </div>
                <?php
                break;

              case 'audio' :
                ?>
                <div class="text-center p-3 mb-3">
                  <audio class="w-100" src="<?=$metaData['request_uri']?>" type="<?=$metaData['mimetype']?>" controls>
                </div>
                <?php
                break;

              case 'image' :
                ?>
                <div class="text-center p-3 mb-3 bg-image rounded">
                  <img class="img-fluid" src="<?=$metaData['request_uri']?>"/></div>
                <?php
                break;
              case 'pdf' :
                ?>
                <object data="<?=$metaData['request_uri']?>" class="w-100" style="min-height:600px;" type="<?=$metaData['mimetype']?>"></object>
                <?php
                break;
              default :
                ?>
                <div class="bg-secondary text-light p-3 rounded text-center p-3 mb-3">
                  <?=L( 'A preview for this file type may not be available in browser.' )?>
                  <div>
                    <embed src="<?=$metaData['request_uri']?>" type="<?=$metaData['mimetype']?>">
                  </div>
                </div>
                <?php
                break;
            }
          }
        }
        ?>


        <?php if ( $section == 'main' && $accessAllowed ) { ?>
          <div class="card border-0 shadow mb-3">
            <div class="card-header bg-dark text-light">
              <i class="fas fa-server fa-fw"></i> <?=L( 'System and Website Info' )?>
            </div>
            <div class="card-body small">
              <div class="row">

                <div class="col-12 col-md-6">
                  <table class="table table-sm table-hover mb-3">
                    <thead class="thead-light">
                    <tr>
                      <th colspan="2"><?=L( 'Website' )?></th>
                    </tr>
                    </thead>
                    <tr>
                      <td><?=L( 'Serial number' )?></td>
                      <td class="text-monospace"><?=implode( '-', str_split( $uuidSettings['uuid'], 4 ) )?>
                        <a href="https://archivarix.com/<?=$_SESSION['archivarix.lang']?>/status/<?=( $uuidSettings['uuidg'] ? $uuidSettings['uuidg'] : $uuidSettings['uuid'] )?>/" target="_blank" rel="nofollow noopener"><i class="fas fa-external-link-alt fa-fw"></i></a>
                      </td>
                    </tr>
                    <tr>
                      <td><?=L( 'Created' )?></td>
                      <td><?=gmdate( "D, d M Y H:i:s", $uuidSettings['created'] )?> GMT</td>
                    </tr>
                    <tr>
                      <td><?=L( 'Restore version' )?></td>
                      <td><?=$uuidSettings['version']?></td>
                    </tr>
                    <tr>
                      <td><?=L( 'History size' )?></td>
                      <td>
                        <?php
                        $backupsStats  = getBackups( 'stats' );
                        $freeDiskSpace = disk_free_space( $sourcePath );
                        if ( empty( $backupsStats['total'] ) ) {
                          echo L( 'None' );
                        } else {
                          ?>
                          <form method="post" action="" class="d-inline">
                            <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                            <input type="hidden" name="action" value="history.edit">
                            <button type="submit" class="btn btn-link btn-sm text-left p-0">
                              <?=number_format( $backupsStats['total'] )?>
                              (<?=getHumanSize( getDirectorySize( $sourcePath . DIRECTORY_SEPARATOR . 'backup' ) )?>)
                              /
                              <i class="far fa-hdd fa-fw"></i> <?=getHumanSize( $freeDiskSpace )?>
                            </button>
                          </form>
                          <?php
                        }
                        if ( $freeDiskSpace < 1e+8 ) { ?>
                          <div class="text-danger text-uppercase">
                            <i class="fas fa-exclamation-triangle"></i> <?=sprintf( L( '%s free disk space left' ), getHumanSize( $freeDiskSpace ) )?>
                          </div>
                        <?php } ?>
                      </td>
                    </tr>
                    <tr>
                      <td><?=L( 'Missing URLs' )?></td>
                      <td>
                        <?php
                        $missingUrlsStats = getMissingUrls( 'stats' );
                        if ( empty( $missingUrlsStats['total'] ) ) {
                          echo L( 'None' );
                        } else {
                          ?>
                          <form method="post" action="" class="d-inline">
                            <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                            <input type="hidden" name="action" value="missing.show">
                            <button type="submit" class="btn btn-link btn-sm text-left p-0">
                              <?=L( 'URLs' ) . ': ' . $missingUrlsStats['total'] . ', ' . L( 'visits' ) . ': ' . $missingUrlsStats['visits']?>
                            </button>
                          </form>
                          <?php
                        }
                        ?>
                      </td>
                    </tr>
                  </table>
                </div>

                <div class="col-12 col-md-6">
                  <table class="table table-sm table-hover mb-3">
                    <thead class="thead-light">
                    <tr>
                      <th colspan="2"><?=L( 'System' )?></th>
                    </tr>
                    </thead>
                    <tr>
                      <td><?=L( 'SQLite version' )?></td>
                      <td><?=getSqliteVersion()?> <?=( version_compare( getSqliteVersion(), '3.7.0', '>=' ) ? '' : '<i class="fas fa-exclamation-circle text-danger" data-toggle="tooltip" title="' . L( 'We have to use a legacy .db file because you have outdated SQLite version. Minimum recommended version is 3.7.0' ) . '"></i>' )?></td>
                    </tr>
                    <tr>
                      <td><?=L( 'PHP version' )?></td>
                      <td><?=phpversion()?></td>
                    </tr>
                    <tr>
                      <td><?=L( 'PHP extensions' )?></td>
                      <td>
                        <?php
                        $recommendedExtensions = ['curl', 'dom', 'fileinfo', 'iconv', 'intl', 'json', 'libxml', 'pdo_sqlite', 'zip',];
                        $missingExtensions     = getMissingExtensions( $recommendedExtensions );
                        foreach ( $recommendedExtensions as $recommendedExtension ) { ?>
                          <span class="badge badge-pill <?=in_array( $recommendedExtension, $missingExtensions ) ? 'badge-danger' : 'badge-success'?>"><?=$recommendedExtension?></span>
                        <?php } ?>
                      </td>
                    </tr>
                    <tr>
                      <td><?=L( 'Max upload size' )?></td>
                      <td><?=getHumanSize( getUploadLimit(), 0 )?></td>
                    </tr>
                    <tr>
                      <td><?=L( 'Max PHP memory' )?></td>
                      <td><?=getHumanSize( getBytesFromHumanSize( ini_get( 'memory_limit' ) ), 0 )?></td>
                    </tr>
                  </table>
                </div>

              </div>
            </div>
          </div>

          <?php printStats( getInfoFromDatabase( $dsn ) ) ?>

          <?php
          $missingExtensions = getMissingExtensions( ['curl', 'zip'] );
          $loaderInfo        = getLoaderInfo();
          ?>
          <div class="card border-0 shadow mb-3">
            <div class="card-header bg-dark text-light">
              <i class="fas fa-sync-alt fa-fw"></i> <?=L( 'System update' )?>
            </div>
            <div class="card-body small">
              <div class="row">
                <div class="col-12 col-md-6">
                  <?=L( 'This tool checks and updates Archivarix CMS, Archivarix Loader to the latest version.' )?> <?=L( 'If you manually edited the source code of those two files, all changes will be lost.' )?> <?=L( 'CMS and Loader settings that were set using the Settings menu will not be affected.' )?></p>
                  <table class="table table-hover table-sm table-responsive mb-0">
                    <thead class="thead-light">
                    <tr>
                      <th class="w-100"><?=L( 'Name' )?></th>
                      <th><?=L( 'Version' )?></th>
                      <th><?=L( 'Filename' )?></th>
                    </tr>
                    </thead>
                    <tr>
                      <td class="text-nowrap"><?=L( 'Archivarix CMS' )?></td>
                      <td class="text-nowrap"><?=ACMS_VERSION?></td>
                      <td class="text-nowrap text-monospace"><?=basename( $_SERVER['SCRIPT_FILENAME'] )?></td>
                    </tr>
                    <tr>
                      <td class="text-nowrap"><?=L( 'Archivarix Loader' )?><?=$loaderInfo['integration'] ? ' + <i class="fab fa-' . $loaderInfo['integration'] . ' fa-fw"></i>' : ''?></td>
                      <td class="text-nowrap"><?=$loaderInfo['version'] ?: L( 'Not detected' )?></td>
                      <td class="text-nowrap text-monospace"><?=$loaderInfo['filename'] ?: L( 'Not detected' )?></td>
                    </tr>
                  </table>

                  <?php if ( !empty( $missingExtensions ) ) { ?>
                    <div class="p-3 rounded bg-danger text-light mt-3 font-weight-bold"><?=sprintf( L( 'This tool requires following PHP extensions to be installed: %s.' ), implode( ', ', $missingExtensions ) )?></div>
                  <?php } ?>
                </div>
                <div class="col-12 col-md-6 text-center d-flex align-items-stretch">
                  <div class="border-left align-self-stretch d-none d-md-block"></div>

                  <div class="mx-auto align-self-center">
                    <div id="messageUpdate" class="p-3 text-left">
                      <div class="text-center">
                        <button type="button" class="btn btn-primary btn-sm" id="checkUpdate"><?=L( 'Check for updates' )?></button>
                      </div>
                    </div>

                    <form action="" method="post" id="form_system_update">
                      <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
                      <input type="hidden" name="action" value="update.system">
                      <input type="hidden" name="urlID" value="<?=!empty( $metaData['rowid'] ) ? $metaData['rowid'] : ''?>"/>
                      <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
                      <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
                      <button type="button" class="btn btn-sm btn-primary btn-action" data-toggle="modal" data-target="#confirm-action" data-source="form_system_update" <?=!empty( $missingExtensions ) ? ' disabled' : ''?>><?=L( 'Update' )?></button>
                    </form>
                  </div>


                </div>

              </div>

            </div>

          </div>

        <?php } ?>
      </div>
    </div>
    </div>
  <?php } ?>


  <!-- MODALS -->
  <div class="modal fade" id="createNewUrl" tabindex="-1" role="dialog" aria-hidden="false">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form action="" method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
          <div class="modal-header bg-primary text-light">
            <h5 class="modal-title"><?=L( 'Create new URL' )?></h5>
            <button type="button" class="close text-light" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <div class="input-group">
                <div class="custom-file">
                  <input type="file" class="custom-file-input input-upload-file" name="create_file" id="input_create_url_file" aria-describedby="button_import_file">
                  <label class="custom-file-label text-truncate" for="input_create_url_file"><?=L( 'Choose file' )?></label>
                </div>
              </div>
              <small class="form-text text-muted"><?=L( 'Leave empty to create an empty file.' )?></small>
            </div>
            <div class="form-group">
              <label for="newUrlPath"><?=L( 'Path' )?></label>
              <input type="text" name="path" class="form-control" id="newUrlPath" placeholder="<?=L( 'Enter a path, e.g. /page.html' )?>" pattern="[/].*" required>
              <div class="invalid-feedback">
                <?=L( 'Enter a new path starting with a slash. This field cannot be empty.' )?>
              </div>
            </div>
            <div class="form-group">
              <label for="newUrlMime"><?=L( 'MIME-type' )?></label>
              <div class="input-group">
                <input type="text" name="mime" class="form-control" id="newUrlMime" placeholder="<?=L( 'Enter a MIME-type' )?>" pattern="[-.+a-zA-Z0-9/]*" required>
                <div class="input-group-append">
                  <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><?=L( 'or select' )?></button>
                  <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item put-new-url-mime" type="button" data-mime="text/html" data-charset="utf-8">HTML</a>
                    <a class="dropdown-item put-new-url-mime" type="button" data-mime="text/css" data-charset="utf-8">CSS</a>
                    <a class="dropdown-item put-new-url-mime" type="button" data-mime="application/javascript" data-charset="utf-8">JAVASCRIPT</a>
                    <a class="dropdown-item put-new-url-mime" type="button" data-mime="text/plain" data-charset="utf-8">TEXT</a>
                    <a class="dropdown-item put-new-url-mime" type="button" data-mime="application/json" data-charset="utf-8">JSON</a>
                    <a class="dropdown-item put-new-url-mime" type="button" data-mime="image/jpeg" data-charset="">JPEG</a>
                    <a class="dropdown-item put-new-url-mime" type="button" data-mime="image/png" data-charset="">PNG</a>
                    <a class="dropdown-item put-new-url-mime" type="button" data-mime="image/gif" data-charset="">GIF</a>
                    <a class="dropdown-item put-new-url-mime text-uppercase" type="button" data-mime="application/octet-stream" data-charset=""><?=L( 'Binary' )?></a>
                  </div>
                </div>
              </div>
            </div>
            <a href="#createNewUrlAdditional" class="text-dark expand-label" data-toggle="collapse" aria-expanded="false" aria-controls="createNewUrlAdditional"><i class="fas fa-caret-right mr-2"></i> <?=L( 'Additional parameters' )?>
            </a>
            <div class="collapse hide mt-2" id="createNewUrlAdditional">
              <div class="form-group">
                <label for="newUrlCharset"><?=L( 'Charset' )?></label>
                <input type="text" name="charset" class="form-control" id="newUrlCharset" placeholder="<?=L( 'Enter a charset if required' )?>" pattern="[-a-zA-Z0-9/]*">
              </div>
              <div class="form-group">
                <label for="newUrlHostname"><?=L( 'Domain' )?></label>
                <input type="text" name="hostname" class="form-control" id="newUrlHostname" placeholder="<?=L( 'Enter a domain name' )?>" pattern="[-a-z0-9.]*" required>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal"><?=L( 'Cancel' )?></button>
            <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
            <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
            <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
            <input type="hidden" name="action" value="create.url">
            <button type="submit" class="btn btn-primary"><?=L( 'Create' )?></button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php if ( $section == 'search' ) { ?>
    <div class="modal fade" id="regexModal" tabindex="-1" role="dialog" aria-labelledby="templateLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
        <div class="modal-content">
          <div class="modal-header bg-primary text-light">
            <h5 class="modal-title"><?=L( 'Regex Cheat Sheet' )?></h5>
            <button type="button" class="close text-light" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div class="row">
              <div class="col-12 col-xl-6 mb-3">
                <h5><?=L( 'Patterns and classes' )?></h5>
                <kbd>[</kbd> <?=L( 'start character class definition' )?><br>
                <kbd>]</kbd> <?=L( 'end character class defintion' )?><br>
                <kbd>(</kbd> <?=L( 'start subpattern' )?><br>
                <kbd>)</kbd> <?=L( 'end subpattern' )?><br>
              </div>
              <div class="col-12 col-xl-6 mb-3">
                <h5><?=L( 'Ranges' )?></h5>
                <kbd>(a|b)</kbd> <?=L( 'a or b' )?><br>
                <kbd>[abc]</kbd> <?=L( 'a or b or c' )?><br>
                <kbd>[^abc]</kbd> <?=L( 'not a and not b and not c' )?><br>
                <kbd>[a-h]</kbd> <?=L( 'letters between a and h (inclusive)' )?><br>
                <kbd>[0-7]</kbd> <?=L( 'digits between 0 and 7 (inclusive)' )?><br>
              </div>
              <div class="col-12 col-xl-6 mb-3">
                <h5><?=L( 'Anchors' )?></h5>
                <kbd>^</kbd> <?=L( 'start of subject' )?><br>
                <kbd>\A</kbd> <?=L( 'start of text' )?><br>
                <kbd>$</kbd> <?=L( 'end of subject' )?><br>
                <kbd>\Z</kbd> <?=L( 'end of text' )?><br>
                <kbd>\b</kbd> <?=L( 'word boundary' )?><br>
                <kbd>\B</kbd> <?=L( 'not a word boundary' )?><br>
              </div>
              <div class="col-12 col-xl-6 mb-3">
                <h5><?=L( 'Base Character Classes' )?></h5>
                <kbd>\s</kbd> <?=L( 'whitespace (space, tab, CRLF)' )?><br>
                <kbd>\S</kbd> <?=L( 'any non whitespace character' )?><br>
                <kbd>\d</kbd> <?=L( 'digits (0-9)' )?><br>
                <kbd>\D</kbd> <?=L( 'any non digit character' )?><br>
                <kbd>\w</kbd> <?=L( '"word" characters (a-z 0-9 _)' )?><br>
                <kbd>\W</kbd> <?=L( 'any non "word" characters' )?><br>
                <kbd>.</kbd> <?=L( 'any character' )?><br>
              </div>
              <div class="col-12 col-xl-6 mb-3">
                <h5><?=L( 'Quantifiers' )?></h5>
                <kbd>n*</kbd> <?=L( 'zero or more' )?><br>
                <kbd>n*?</kbd> <?=L( 'ungreedy zero or more' )?><br>
                <kbd>n+</kbd> <?=L( 'one or more' )?><br>
                <kbd>n+?</kbd> <?=L( 'ungreedy one or more' )?><br>
                <kbd>n?</kbd> <?=L( 'zero or one occurrence' )?><br>
                <kbd>n??</kbd> <?=L( 'ungreedy zero or one occurrence' )?><br>
                <kbd>{n}</kbd> <?=L( 'n occurrences exactly' )?><br>
                <kbd>{n,}</kbd> <?=L( 'at least n occurrences' )?><br>
                <kbd>{,m}</kbd> <?=L( 'at most m occurrences' )?><br>
                <kbd>{n,m}</kbd> <?=L( 'between n and m occurrences (inclusive)' )?><br>
                <kbd>{n,m}?</kbd> <?=L( 'ungreedy between n and m occurrences (inclusive)' )?><br>
              </div>
              <div class="col-12 col-xl-6 mb-3">
                <h5><?=L( 'Subpattern Modifiers &amp; Assertions' )?></h5>
                <kbd>(?=)</kbd> <?=L( 'positive look ahead assertion' )?><br>
                <kbd>(?!)</kbd> <?=L( 'negative look ahead assertion' )?><br>
                <kbd>(?&lt;=)</kbd> <?=L( 'positive look behind assertion' )?><br>
                <kbd>(?&lt;!)</kbd> <?=L( 'negative look behind assertion' )?><br>
                <kbd>(?&gt;)</kbd> <?=L( 'once-only subpattern' )?><br>
                <kbd>(?(x))</kbd> <?=L( 'conditional subpattern (if then)' )?><br>
                <kbd>(?(x)|)</kbd> <?=L( 'conditional subpattern (if then else)' )?><br>
                <kbd>(?#)</kbd> <?=L( 'comment' )?><br>
                <kbd>(?:)</kbd> <?=L( 'non capturing subpattern' )?><br>
              </div>
            </div>
          </div>
          <div class="modal-footer border-0">
            <button type="button" class="btn btn-secondary" data-dismiss="modal"><?=L( 'Close' )?></button>
          </div>
        </div>
      </div>
    </div>
  <?php } ?>
  <?php if ( $section == 'backups' ) { ?>
    <div class="modal fade" id="breakpointModal" tabindex="-1" role="dialog" aria-hidden="false">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form action="" method="post" class="needs-validation" novalidate>
            <div class="modal-header bg-primary text-light">
              <h5 class="modal-title"><?=L( 'New breakpoint' )?></h5>
              <button type="button" class="close text-light" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body">
              <div class="form-group">
                <label for="breakpointName"><?=L( 'Name' )?></label>
                <input type="text" name="name" class="form-control" id="breakpointName" placeholder="<?=L( 'Enter a name or a description' )?>" pattern=".+" required>
                <div class="invalid-feedback">
                  <?=L( 'Enter a name or a short description that you can understand later.' )?>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-default" data-dismiss="modal"><?=L( 'Cancel' )?></button>
              <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
              <input type="hidden" name="action" value="history.breakpoint.create">
              <button type="submit" class="btn btn-primary"><?=L( 'Create' )?></button>
            </div>
          </form>
        </div>
      </div>
    </div>
  <?php } ?>

  <div class="alert alert-success text-center position-fixed" id="savedAlert" role="alert" style="display: none; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 99;"></div>

  <div class="position-fixed d-none">
    <form id="formUrlsPage" action="" method="post">
      <input type="text" class="pageNumber" name="page">
      <input type="text" class="domainName" name="domain">
      <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
      <input type="hidden" name="action" value="change.page">
    </form>
  </div>

  <?php
  if ( isset( $metaData['rowid'] ) ) {
    ?>
    <div class="modal fade" id="cloneModal" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
          <form action="" method="post" class="needs-validation" novalidate>
            <div class="modal-header bg-primary text-light">
              <h5 class="modal-title"><?=L( 'Clone URL' )?></h5>
              <button type="button" class="close text-light" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body">
              <div class="form-group">
                <label for="cloneUrlPath"><?=L( 'Complete URL path' )?></label>
                <input type="text" class="form-control mb-2 px-3 clear" name="cloneUrlPath" id="cloneUrlPath" value="<?=htmlspecialchars( rawurldecode( $metaData['request_uri'] ), ENT_IGNORE )?>" required/>
                <small class="form-text text-muted"><?=L( 'We recommend creating clones in the same directory as originals.' )?></small>
              </div>
              <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
              <input type="hidden" name="action" value="clone.url"/>
              <input type="hidden" name="show" value="edit.url"/>
              <input type="hidden" name="urlID" id="cloneUrlID" value="<?=!empty( $metaData['rowid'] ) ? $metaData['rowid'] : ''?>"/>
              <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
              <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
            </div>
            <div class="modal-footer border-0">
              <button type="submit" class="btn btn-primary"><?=L( 'Clone' )?></button>
              <button type="button" class="btn btn-secondary" data-dismiss="modal"><?=L( 'Close' )?></button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="modal fade" id="templateModal" tabindex="-1" role="dialog" aria-labelledby="templateLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
          <form action="" method="post" class="needs-validation" novalidate>
            <div class="modal-header bg-primary text-light">
              <h5 class="modal-title"><?=L( 'Create template' )?></h5>
              <button type="button" class="close text-light" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body">
              <div class="form-group">
                <label for="templateName"><?=L( 'Template name' )?></label>
                <input type="text" class="form-control mb-2 px-3 clear" name="name" id="templateName" value="" pattern="[-\w]{1,64}" required/>
                <small class="form-text text-muted"><?=L( 'Template name can only contain latin letters, numbers, dashes and underscores.' )?></small>
              </div>
              <input type="hidden" name="xsrf" value="<?=$_SESSION['acms_xsrf']?>">
              <input type="hidden" name="action" value="create.template"/>
              <input type="hidden" name="urlID" value="<?=!empty( $metaData['rowid'] ) ? $metaData['rowid'] : ''?>"/>
              <input type="hidden" name="filterValue" class="filterValue" value="<?=htmlspecialchars( $filterValue )?>"/>
              <input type="hidden" name="urlOffsets" class="urlOffsets" value="<?=htmlspecialchars( serialize( $urlOffsets ) )?>"/>
            </div>
            <div class="modal-footer border-0">
              <button type="submit" class="btn btn-primary"><?=L( 'Create' )?></button>
              <button type="button" class="btn btn-secondary" data-dismiss="modal"><?=L( 'Close' )?></button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php
  }

} // if logged
?>

<div class="modal hide" id="pleaseWaitDialog" data-backdrop="static" data-keyboard="false">
  <div class="container d-flex align-items-center h-100">
    <div class="w-100 text-center">
      <div class="spinner-grow text-warning mb-3" style="width: 10rem; height: 10rem;" role="status">
      </div>
      <div class="text-light h3"><?=L( 'Processing' )?>&hellip;</div>
      <div class="text-light h5 message"><?=L( 'Do not close the browser window!' )?></div>
    </div>
  </div>
</div>

<div class="modal fade" id="confirm-action" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body">
        <h5><?=L( 'Confirm action' )?></h5>
        <div class="confirm-message"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default btn-danger" data-dismiss="modal">
          <i class="fas fa-times fa-fw"></i> <?=L( 'Cancel' )?></button>
        <a class="btn btn-success btn-ok text-white" data-source=""><i class="fas fa-check fa-fw"></i> <?=L( 'Confirm' )?>
        </a>
      </div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.1/umd/popper.min.js" integrity="sha256-/ijcOLwFf26xEYAjW75FizKVo5tnTYiQddPZoLUHHZ8=" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.5.0/js/bootstrap.min.js" integrity="sha256-OFRAJNoaD8L3Br5lglV7VyLRf0itmoBzWUoM+Sji4/8=" crossorigin="anonymous"></script>
<?php if ( isset( $missingUrls ) || isset ( $backups ) ) { ?>
  <script src="https://unpkg.com/tableexport.jquery.plugin/tableExport.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/1.5.3/jspdf.min.js" integrity="sha256-gJWdmuCRBovJMD9D/TVdo4TIK8u5Sti11764sZT1DhI=" crossorigin="anonymous"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.3/jspdf.plugin.autotable.min.js" integrity="sha256-8Wa9evITaf3eZpKzFIrEfVrQycFZ1C/IwuAwUkXnB94=" crossorigin="anonymous"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-table/1.16.0/bootstrap-table.min.js" integrity="sha256-JFzlEUS2cZGdNFhVNH3GSFuqZFLjzWIjOqG5BY+Yhvw=" crossorigin="anonymous"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-table/1.16.0/extensions/export/bootstrap-table-export.min.js" integrity="sha256-lIbbLxDO4ya44d6O35r/Gd+w/JZfueaLlMKex9vjE8k=" crossorigin="anonymous"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-table/1.16.0/locale/bootstrap-table-ru-RU.min.js" integrity="sha256-yiXMSg2viGB8FLoDVevyuUmT23fH7ayBHKPBAkTbL3k=" crossorigin="anonymous"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-table/1.16.0/locale/bootstrap-table-en-US.min.js" integrity="sha256-x1/4wPclkUa7ixBhceaGT2MyU+9q247CTQrjkxLivr4=" crossorigin="anonymous"></script>
<?php } ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.9/jstree.min.js" integrity="sha256-NIC//Hx8LD7I4tEDlSEyT6iXZoB4t4KryN8MAKzqsP8=" crossorigin="anonymous"></script>
<?php if ( in_array( $section, ['settings', 'template', 'htaccess'] ) || isset( $metaData['rowid'] ) ) { ?>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.54.0/codemirror.min.js" integrity="sha256-zW3RviTWD2qcjPDA/ByugxSFO5EPHb8Yl0Z6o7X9Zqk=" crossorigin="anonymous"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.54.0/addon/dialog/dialog.min.js" integrity="sha256-PtFqyuUjaTs/6U88zUmtE1zM6upb08PcEGeZtFLc29k=" crossorigin="anonymous"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.54.0/addon/display/autorefresh.min.js" integrity="sha256-vcyC0BwctXTX+4u/STghOoETEWM3XVyGknLeOFkkUhY=" crossorigin="anonymous"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.54.0/addon/edit/matchbrackets.min.js" integrity="sha256-UUl0n6E68L43ox5dEYWi6i3mmGF8QFns7cHk9yahwEA=" crossorigin="anonymous"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.54.0/addon/edit/matchtags.min.js" integrity="sha256-/iiXk7AuPSuyg+HvCoN5C2SY0jWwfwizV8dAgMney64=" crossorigin="anonymous"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.54.0/addon/search/jump-to-line.min.js" integrity="sha256-rkiM7TLe6okStJ3A5qnXnHtZpzw1SZRVfJe2n3Vo7C4=" crossorigin="anonymous"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.54.0/addon/search/search.min.js" integrity="sha256-Q4uueIAO4qx13vAcbo3PViy+o7UPw7+tcmDxTsrEN4g=" crossorigin="anonymous"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.54.0/addon/search/searchcursor.min.js" integrity="sha256-CHRW7GGCp6vNtEW1ZUec9/MnCFeZeCHZLkSavsuKrWc=" crossorigin="anonymous"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.54.0/addon/selection/active-line.min.js" integrity="sha256-sKJy8iztj8x6uNT0rUk8jnhwMGSbcSmCqpvRkgKF44A=" crossorigin="anonymous"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.54.0/mode/css/css.min.js" integrity="sha256-AmHwHDNYa4UtdlIzirPIDXHNwkEfr0NI6AeBnXs6L2c=" crossorigin="anonymous"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.54.0/mode/htmlmixed/htmlmixed.min.js" integrity="sha256-GQZ9+vs5r8hhzJxB1fgAojLiI4Me/rpFHzPiUHh1Wks=" crossorigin="anonymous"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.54.0/mode/javascript/javascript.min.js" integrity="sha256-HMQjETPj1Zu+Gm6UcpLTq8mnRcEhZPsUcfGfaftO0BE=" crossorigin="anonymous"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.54.0/mode/php/php.min.js" integrity="sha256-OA4FwkvfbTgk2NO07G7I+llaAeb5zoM+ShY9L/zCHAU=" crossorigin="anonymous"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.54.0/mode/xml/xml.min.js" integrity="sha256-haiMC/PTdYEeEyY0Jgz9E4YAm44UHTwvwDBcd0FmAkU=" crossorigin="anonymous"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.7.1/dropzone.min.js" integrity="sha256-fegGeSK7Ez4lvniVEiz1nKMx9pYtlLwPNRPf6uc8d+8=" crossorigin="anonymous"></script>
<?php } ?>
<?php if ( in_array( $section, ['settings'] ) ) { ?>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.10.1/Sortable.min.js" integrity="sha256-9D6DlNlpDfh0C8buQ6NXxrOdLo/wqFUwEB1s70obwfE=" crossorigin="anonymous"></script>
<?php } ?>
<?php if ( isset( $metaData['rowid'] ) || in_array( $section, ['template', 'page'] ) ) { ?>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/4.9.6/tinymce.min.js" integrity="sha256-W+XMAh5gT0s+uD0YFtzN1WgYSw+qrTZ3EPag+WcdjPM=" crossorigin="anonymous"></script>
<?php } ?>

<script language="JavaScript">
  (function () {
    'use strict';
    window.addEventListener('load', function () {
      var forms = document.getElementsByClassName('needs-validation');
      var validation = Array.prototype.filter.call(forms, function (form) {
        form.addEventListener('submit', function (event) {
          if (form.checkValidity() === false) {
            event.preventDefault();
            event.stopPropagation();
          }
          form.classList.add('was-validated');
        }, false);
      });
    }, false);
  })();

  var submitNonAjax = function formSubmitNonAjax(form_id) {
    $('#savedAlert').html('<?=L( 'Sending with AJAX failed. Sending data the regular way' )?>&hellip;');
    $('#savedAlert').show('fast').delay(5000).hide('fast');
    $('#' + form_id + ' input[name=ajax]').remove();
    setTimeout(function () {
      $('#' + form_id).removeAttr('onsubmit').submit();
    }, 4000);
  };

  $(function () {
    $('[data-toggle="tooltip"]').tooltip();
    $('.toast').toast('show');
  });

  $('#form_confirm_replace, #form_confirm_remove, #form_convert_utf8, #form_update_external_links, #form_update_encoded_urls, #form_system_update, #form_task_part, [id^=form_history_recover_], [id^=form_import_], [id^=form_remove_]').submit(function (e) {
    if (this.checkValidity() === false) {
      e.preventDefault();
      e.stopPropagation();
      return;
    }
    e.preventDefault();
    this.submit();
    $('#pleaseWaitDialog').modal();
  });

  <?php if($taskIncomplete) { ?>
  $('#pleaseWaitDialog .message').html('<?=sprintf( L( 'Processed: %s' ), number_format( unserialize( $taskStats )['pages'] ), 2 )?>');
  $('#form_task_part').submit();
  <?php } ?>

  $('.btn-action').on('click touch', function () {
    $('#confirm-action .confirm-message').html($(this).data('message'));
    $('#confirm-action .btn-ok').attr('data-source', $(this).attr('data-source'));
  });

  $('.btn-ok').on('click touch', function () {
    $('#confirm-action').modal('hide');
    var datasource = $(this).attr('data-source');
    $('#' + datasource).submit();
  });

  $('.password-eye').on('click touch', function () {
    $(this).toggleClass('fa-eye').toggleClass('fa-eye-slash');
    var type = $(this).closest('.input-group').children('input').attr("type");
    if (type == "text") $(this).closest('.input-group').children('input').attr("type", "password"); else $(this).closest('.input-group').children('input').attr("type", "text");
  });

  $('.clipboard-copy').on('click touch', function () {
    var $temp = $("<input>");
    $("body").append($temp);
    $temp.val($(this).attr('data-clipboard-text')).select();
    document.execCommand("copy");
    $temp.remove();
    $('#savedAlert').html('<?=L( 'Copied to clipboard' )?>');
    $('#savedAlert').show('fast').delay(4000).hide('fast');
  });

  $('.loader-settings-rules-wrapper').on('click touch', '.remove-loader-custom-rule', function () {
    $(this).closest('.loader-custom-rule-block').fadeOut(500, function () {
      $(this).remove();
    });
  });

  $('#create-loader-custom-rule').on('click touch', function () {
    newRule = $('.loader-custom-rule-block.d-none').clone();
    newRule.removeClass('d-none');
    newRule.appendTo('.loader-settings-rules-wrapper');
  });

  $('.loader-settings-rules-wrapper').on('click touch', '.put-custom-file', function () {
    $(this).closest('.input-group').children('input').val($(this).data('filename'));
  });

  $('.loader-settings-rules-wrapper').on('click touch', '.put-custom-rule', function () {
    $(this).closest('.loader-custom-rule-block').find('.input-custom-keyphrase').val($(this).data('keyphrase'));
    $(this).closest('.loader-custom-rule-block').find('.select-custom-regex').val($(this).data('regex')).change();
    $(this).closest('.loader-custom-rule-block').find('.input-custom-limit').val($(this).data('limit'));
    $(this).closest('.loader-custom-rule-block').find('.select-custom-position').val($(this).data('position')).change();
  });

  // filter rules
  $('.search-code-add-filter').on('click touch', function () {
    newRule = $('.search-filter-block.d-none').clone();
    newRule.removeClass('d-none');
    newRule.appendTo('.search-code-filter-wrapper');
  });

  $('.search-url-add-filter').on('click touch', function () {
    newRule = $('.search-filter-block.d-none').clone();
    newRule.removeClass('d-none');
    newRule.appendTo('.search-url-filter-wrapper');
  });

  $('.search-filter-wrapper').on('click touch', '.remove-search-filter', function () {
    $(this).closest('.search-filter-block').fadeOut(500, function () {
      $(this).remove();
    });
  });

  $('.search-filter-wrapper').on('change', '.search-filter-param', function () {
    searchFilterTag = $(this).find('option:selected').data('tag');
    searchFilterType = $(this).find('option:selected').data('type');
    searchFilterRegex = $(this).find('option:selected').data('regex');
    searchFilterOperators = $(this).find('option:selected').data('operators').split(',');
    searchFilterPlaceholder = $(this).find('option:selected').data('placeholder');
    searchFilterTextCurrent = $(this).closest('.search-filter-block').find('.search-filter-text');
    searchFilterSelectOperator = $(this).closest('.search-filter-block').find('.search-filter-operator');
    searchFilterValue = searchFilterTextCurrent.val();
    searchFilterText = $(document.createElement(searchFilterTag)).attr('type', searchFilterType);
    searchFilterText.attr({
      name: "filter[text][]",
      class: "form-control search-filter-text  text-monospace",
      placeholder: searchFilterPlaceholder
    });
    searchFilterText.val(searchFilterValue);
    searchFilterTextCurrent.replaceWith(searchFilterText);
    if (searchFilterRegex) {
      $(this).closest('.search-filter-block').find('.search-filter-regex-div').removeClass('d-none');
    } else {
      $(this).closest('.search-filter-block').find('.search-filter-regex-div').addClass('d-none');
    }
    searchFilterSelectOperator.find('option').each(function () {
      if (searchFilterOperators.includes($(this).val())) {
        $(this).removeAttr('disabled hidden');
      } else {
        $(this).removeAttr('selected').attr({disabled: true, hidden: true});
      }
    });
    searchFilterSelectOperator.find('option:not([disabled]):first').prop('selected', true);
  });

  $('.search-filter-wrapper').on('change', '.search-filter-regex-checkbox', function () {
    if (this.checked) {
      $(this).prev().val(1);
    } else {
      $(this).prev().val(0);
    }
  });

  $('.constructor-param').on('click touch', function () {
    constructorInput = $(this).closest('.constructor-wrapper').find('.input-constructor');
    if (constructorInput.val().slice(constructorInput.val().length - 1) == '/') {
      constructorInput.val(constructorInput.val().slice(0, -1));
    }
    if ($(this).data('slash')) {
      constructorInput.val(constructorInput.val() + '/' + $(this).data('value') + '/');
    } else {
      constructorInput.val(constructorInput.val() + $(this).data('value'));
    }
  });

  $('.play-youtube').on('click touch', function () {
    $('.videos-welcome-list').addClass('justify-content-center');
    $('.play-youtube').removeClass('text-primary').addClass('text-dark');
    $(this).removeClass('text-dark').addClass('text-primary');
    $('.player-youtube').show(1000);
    $('.player-youtube iframe').attr('src', 'https://www.youtube.com/embed/' + $(this).data('videoid') + '?autoplay=1&rel=0&modestbranding=1&hl=<?=$_SESSION['archivarix.lang']?>');
  });

  $('.input-resize-auto').keydown(resizeInput).keyup(resizeInput).each(resizeInput);

  $('.input-clear').on('click touch', function () {
    $(this).closest('.input-group').find('input').val('');
  });

  $('#createNewUrl').on('shown.bs.modal', function (e) {
    var trigger = $(e.relatedTarget);
    $('#newUrlHostname').val(trigger.data('hostname'));
  });

  $('#templateModal').on('shown.bs.modal', function (e) {
    $('#templateName').focus();
  });

  $('#breakpointModal').on('shown.bs.modal', function (e) {
    $('#breakpointName').focus();
  });

  $('#clickSearchNew').on('click touch', function () {
    $('#formSearchNew').submit();
  });

  $('#clickToolsView').on('click touch', function () {
    $('#formToolsView').submit();
  });

  $('#clickHistory').on('click touch', function () {
    $('#formHistoryNew').submit();
  });

  $('#clickSettings').on('click touch', function () {
    $('#formSettingsView').submit();
  });

  $('.changePage').on('click touch', function () {
    $('#formUrlsPage .pageNumber').val($(this).data('page'));
    $('#formUrlsPage .domainName').val($(this).data('domain'));
    $('#formUrlsPage').submit();
  });

  $(".check-all").click(function () {
    $("." + $(this).data("group")).prop('checked', $(this).prop('checked'));
  });

  $(".stats-collapse").on('shown.bs.collapse', function () {
    window["drawStatsMimeCount_" + $(this).data('id')]();
    window["drawStatsMimeSize_" + $(this).data('id')]();
    window["drawStatsHostnames_" + $(this).data('id')]();
  });

  $('.input-upload-file').on('change', function () {
    var importFileName = $(this)[0].files[0].name;
    $(this).next('.custom-file-label').text(importFileName);
  });

  $('#input_create_url_file').on('change', function () {
    $('#newUrlPath').val('/' + $(this)[0].files[0].name);
    $('#newUrlMime').val($(this)[0].files[0].type);
  });

  $(".put-new-url-mime").on('click touch', function () {
    $('#newUrlMime').val($(this).data('mime'));
    $('#newUrlCharset').val($(this).data('charset'));
  });

  <?php if ( isset( $missingUrls ) || isset ( $backups ) ) { ?>
  $('#historyRecoverSelected').on('click touch', function () {
    rows = [];
    var $table = $('#table_history');
    $table.bootstrapTable('getSelections').forEach(function (row) {
      rows.push(row.id);
    });
    var url = "<?=$_SERVER['REQUEST_URI']?>";
    $.post({
      url: url,
      data: {
        action: "history.recover",
        ajax: 1,
        xsrf: "<?=$_SESSION['acms_xsrf']?>",
        backups: rows.join(),
      },
      success: function (data) {
        rows.forEach(function (row) {
          $table.bootstrapTable('removeByUniqueId', row);
        });
      },
      error: function (data) {
        submitNonAjax('historyRecoverSelected');
      }
    });
  });

  $('#historyPurgeSelected').on('click touch', function () {
    rows = [];
    var $table = $('#table_history');
    $table.bootstrapTable('getSelections').forEach(function (row) {
      rows.push(row.id);
    });
    var url = "<?=$_SERVER['REQUEST_URI']?>";
    $.post({
      url: url,
      data: {
        action: "history.purge",
        ajax: 1,
        xsrf: "<?=$_SESSION['acms_xsrf']?>",
        backups: rows.join(),
      },
      success: function (data) {
        rows.forEach(function (row) {
          $table.bootstrapTable('removeByUniqueId', row);
        });
      },
      error: function (data) {
        submitNonAjax('historyPurgeSelected');
      }
    });
  });
  <?php } ?>

  $('#checkUpdate').on('click touch', function () {
    $.getJSON("https://archivarix.com/<?=$_SESSION['archivarix.lang']?>/cms/?ver=<?=ACMS_VERSION?>&uuid=<?=$uuidSettings['uuid']?>", function (json) {
      $('#messageUpdate').html(json.message);
    });
  });

  $('#code-tab').on('shown.bs.tab', function () {
    editor.refresh();
  });

  $('#div_create_custom_file').on('shown.bs.collapse', function () {
    editor.refresh();
  });

  $('#showUnmodified').change(function () {
    if (this.checked) $('.revisions span').css('display', 'block');
    else $('.revisions span').css('display', 'none');
  });

  function resizeInput() {
    $(this).attr('size', ($(this).val().length ? $(this).val().length : 1));
  }

  function ajaxRemoveURL(form) {
    var url = "<?=$_SERVER['REQUEST_URI']?>";
    $.post({
      url: url,
      data: $('#' + form).serialize(),
      dataType: 'json',
      success: function (data) {
        $('#' + form).closest('.search-result').hide('fast', function () {
          $(this).remove();
        });
      },
      error: function (data) {
        $('#savedAlert').html('<?=L( 'Sending with AJAX failed. Your server blocks XHR POST requests.' )?>');
        $('#savedAlert').show('fast').delay(5000).hide('fast');
      }
    });
    return false;
  }

  function ajaxSaveFile(form) {
    if (form == 'formHtml') {
      tinymce.triggerSave();
    }
    if (form == 'formCode') {
      editor.save();
    }
    if (form == 'formText') {
      editor.save();
    }
    if (form == 'formCustomFile') {
      return;
    }
    if (form == 'formCreateCustomFile') {
      return;
    }
    if (form == 'formSimpleHtml') {
      return;
    }

    var url = "<?=$_SERVER['REQUEST_URI']?>";
    $.post({
      url: url,
      data: $('#' + form).serialize(),
      dataType: 'json',
      //contentType: 'application/json',
      success: function (data) {
        $('#savedAlert').html('<?=L( 'Saved' )?>');
        if (form == 'formHtml') {
          editor.setValue($('#textarea_html').val());
          $('#savedAlert').show('fast').delay(2000).hide('fast');
        }
        if (form == 'formCode') {
          tinymce.editors[0].setContent($('#textarea_text').val());
          tinymce.editors[0].theme.resizeTo('100%', 500);
          tinymce.editors[0].focus();
          $('#savedAlert').show('fast').delay(2000).hide('fast');
        }
        if (form == 'formText') {
          $('#savedAlert').show('fast').delay(2000).hide('fast');
        }
        if (form == 'formCustomFile') {
          return false;
        }
        if (form == 'formCreateCustomFile') {
          return false;
        }
        if (form == 'formSimpleHtml') {
          return false;
        }
      },
      error: function (data) {
        submitNonAjax(form);
      }
    });
    return false;
  }


  function post(path, parameters) {
    var form = $('<form></form>');

    form.attr("method", "post");
    form.attr("action", path);

    $.each(parameters, function (key, value) {
      var field = $('<input></input>');

      field.attr("type", "hidden");
      field.attr("name", key);
      field.attr("value", value);

      form.append(field);
    });

    $(document.body).append(form);
    form.submit();
  }


  // JSTree
  $('[id^=jstree_]').each(function () {
    $(this).jstree({
      "plugins": [
        "wholerow",
        "search",
        "sort",
        "state",
      ],
      "search": {
        "case_sensitive": false,
        "show_only_matches": true
      },
      "core": {
        "themes": {
          "variant": "small",
        },
        "multiple": false,
      },
      "state": {
        "key": "domainname",
      },
      "sort": function (a, b) {
        a1 = this.get_node(a);
        b1 = this.get_node(b);
        if (a1.data.jstree.order == b1.data.jstree.order) {
          return (a1.text > b1.text) ? 1 : -1;
        } else {
          return (a1.data.jstree.order > b1.data.jstree.order) ? 1 : -1;
        }
      },
    })
  });

  $('[id^=jstree_]')
    .on('activate_node.jstree', function (e, data) {
      post('//' + $(this).data("domain-converted") + '<?=htmlspecialchars( $_SERVER['REQUEST_URI'] )?>', {
        urlID: data.instance.get_node(data.node.id).data.jstree.id,
        show: "edit.url",
        filterValue: $('#treeSearch').val(),
        sender: "jstree",
      });
    });

  $('.template-link').on('click touch', function () {
    var url = "<?=$_SERVER['REQUEST_URI']?>";
    post(url,
      {
        show: "edit.template",
        xsrf: "<?=$_SESSION['acms_xsrf']?>",
        name: $(this).data('name'),
      });
  });

  $('.template-create-page-link').on('click touch', function () {
    var url = "<?=$_SERVER['REQUEST_URI']?>";
    post(url,
      {
        action: "show.create.page",
        xsrf: "<?=$_SESSION['acms_xsrf']?>",
        name: $(this).data('name'),
      });
  });

  $('#template-param-panel button').on('click touch', function () {
    $('#template-param-type').html($(this).data('type'));
    if ($(this).data('name')) {
      $('#template-param-name').val($(this).data('name'));
      $('#template-param-name').each(resizeInput);
    }
  });

  $('#template-param-insert').on('click touch', function () {
    editor.replaceSelection("{{@" + $('#template-param-type').html() + "('" + $('#template-param-name').val() + "')}}");
  });


  $(".urls-tree-expand").on('click touch', function () {
    if ($("#" + $(this).data("jstree")).find(".jstree-open").length !== 0) {
      $("#" + $(this).data("jstree")).jstree('close_all');
    } else {
      $("#" + $(this).data("jstree")).jstree('open_all');
    }
  });

  urlshidden = localStorage.urlshidden === undefined ? new Array() : JSON.parse(localStorage.urlshidden);
  for (var i in urlshidden) {
    if ($("#" + urlshidden[i]).hasClass('urls-collapse')) {
      $("#" + urlshidden[i]).collapse("hide");
    }
  }

  $(".urls-collapse").on('hidden.bs.collapse', function () {
    var active = $(this).attr('id');
    var urlshidden = localStorage.urlshidden === undefined ? new Array() : JSON.parse(localStorage.urlshidden);
    if ($.inArray(active, urlshidden) == -1) urlshidden.push(active);
    localStorage.urlshidden = JSON.stringify(urlshidden);
  });

  $(".urls-collapse").on('shown.bs.collapse', function () {
    var active = $(this).attr('id');
    var urlshidden = localStorage.urlshidden === undefined ? new Array() : JSON.parse(localStorage.urlshidden);
    var elementIndex = $.inArray(active, urlshidden);
    if (elementIndex !== -1) urlshidden.splice(elementIndex, 1);
    localStorage.urlshidden = JSON.stringify(urlshidden);
  });

  // JSTree search action
  var to = false;
  $('#treeSearch').keyup(function () {
    if (to) {
      clearTimeout(to);
    }
    to = setTimeout(function () {
      var v = $('#treeSearch').val();
      $('.filterValue').val(v);
      $('[id^=jstree_]').each(function () {
        $(this).jstree(true).search(v)
      });
    }, 250);
  });

  // JSTree search if any
  var v = $('#treeSearch').val();
  if (v) {
    $('[id^=jstree_]').each(function () {
      $(this).jstree(true).search(v)
    });
  }

  <?php if (in_array( $section, ['settings'] )) { ?>
  new Sortable(sortwrapper, {
    handle: '.sort-handle',
    swapThreshold: 0.40,
    animation: 500,
    ghostClass: 'border-dark',
  });
  <?php } ?>

  <?php if (in_array( $section, ['settings', 'template'] ) || isset( $metaData['rowid'] )) { ?>
  // Dropzone
  Dropzone.options.urlUpload = {
    uploadMultiple: false,
    maxFiles: 1,
    rowID: "<?=$documentID?>",
    "filterValue": $('#treeSearch').val(),
    maxFilesize: <?=floor( getUploadLimit() / 1024 / 1024 )?>,
    addRemoveLinks: true,
    init: function () {
      this.on('success', function () {
        if (this.getUploadingFiles().length === 0 && this.getQueuedFiles().length === 0) {
          post('', {
            "urlID": Dropzone.options.urlUpload.rowID,
            "show": "edit.url",
            "filterValue": $('#treeSearch').val(),
            "sender": "dropzone",
          });
        }
      });
    }
  };

  // Dropzone custom file
  Dropzone.options.customFileUpload = {
    uploadMultiple: false,
    maxFiles: 1,
    maxFilesize: <?=floor( getUploadLimit() / 1024 / 1024 )?>,
    addRemoveLinks: true,
    init: function () {
      this.on('success', function () {
        if (this.getUploadingFiles().length === 0 && this.getQueuedFiles().length === 0) {
          post('', {
            "filterValue": $('#treeSearch').val(),
            "sender": "dropzone",
            "xsrf": "<?=$_SESSION['acms_xsrf']?>",
            "action": "upload.custom.file",
          });
        }
      });
    }
  };

  // CodeMirror
  if (document.getElementById('textarea_text')) {
    var editor = CodeMirror.fromTextArea(document.getElementById('textarea_text'), {
      mode: "<?=$documentMimeType?>",
      viewportMargin: Infinity,
      lineNumbers: true,
      lineWrapping: true,
      smartIndent: true,
      matchBrackets: true,
      styleActiveLine: true,
      //matchTags: {bothTags: true},
      extraKeys: {
        "Ctrl-S": function (instance) {
          ajaxSaveFile($('#textarea_text').closest('form').attr('id'));
        },
      },
    });
    editor.setSize("100%", "100%");
  }
  <?php } ?>

  $('.codemirror-editor').each(function () {
    CodeMirror.fromTextArea(this, {
      mode: $(this).data('mime'),
      viewportMargin: Infinity,
      lineNumbers: true,
      lineWrapping: true,
      smartIndent: true,
      matchBrackets: true,
      styleActiveLine: true,
      readOnly: $(this).data('readonly'),
    });
  });

  <?php if (isset( $metaData['rowid'] ) || in_array( $section, ['template'] )) { ?>
  // TinyMCE
  tinyEditor = tinymce.init({
    selector: 'textarea#textarea_html',
    //language: 'en_US',
    plugins: "advlist autoresize lists charmap print anchor textcolor visualblocks colorpicker  fullpage fullscreen code image link imagetools media searchreplace save",
    toolbar: "fullscreen fullpage | insert | undo redo |  formatselect | bold italic forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | code image link imagetools media searchreplace save",
    removed_menuitems: 'newdocument',
    save_onsavecallback: function () {
      ajaxSaveFile('formHtml');
    },
    valid_elements: "*[*]",
    valid_children: "*[*]",
    theme: "modern",
    cleanup_on_startup: false,
    trim_span_elements: false,
    verify_html: false,
    cleanup: false,
    extended_valid_elements: "*[*]",
    custom_elements: "*[*]",
    allow_conditional_comments: true,
    allow_html_in_named_anchor: true,
    allow_unsafe_link_target: true,
    convert_fonts_to_spans: false,
    branding: false,
    height: 900,
    autoresize_on_init: true,
    relative_urls: true,
    allow_script_urls: true,
    convert_urls: false,
    remove_script_host: true,
    anchor_bottom: false,
    anchor_top: false,
    forced_root_block: false,
    keep_styles: true,
    remove_trailing_brs: false,
    document_base_url: "<?=htmlspecialchars( $documentBaseUrl )?>",
    entity_encoding: "named",
  });
  <?php } ?>

  <?php if (in_array( $section, ['page'] )) { ?>
  tinymce.init({
    selector: 'textarea.tinymce-control',
    menubar: false,
    valid_elements: "*[*]",
    valid_children: "*[*]",
    theme: "modern",
    cleanup_on_startup: false,
    trim_span_elements: false,
    verify_html: false,
    cleanup: false,
    extended_valid_elements: "*[*]",
    custom_elements: "*[*]",
    allow_conditional_comments: true,
    allow_html_in_named_anchor: true,
    allow_unsafe_link_target: true,
    convert_fonts_to_spans: false,
    branding: false,
    autoresize_on_init: true,
    relative_urls: true,
    allow_script_urls: true,
    convert_urls: false,
    remove_script_host: true,
    anchor_bottom: false,
    anchor_top: false,
    forced_root_block: false,
    keep_styles: true,
    remove_trailing_brs: false,
    entity_encoding: "named",
    autoresize_bottom_margin: 30,
    plugins: [
      'advlist autolink lists link image charmap print preview anchor textcolor',
      'autoresize searchreplace visualblocks code fullscreen',
      'insertdatetime media image table contextmenu paste code wordcount'
    ],
    toolbar: 'insert | undo redo |  formatselect | bold italic backcolor  | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | image media | removeformat | code',
  });
  <?php  } ?>
</script>
</body>
</html>