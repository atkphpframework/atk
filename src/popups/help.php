<?php
/**
 * This file is part of the ATK distribution on GitHub.
 * Detailed copyright and licensing information can be found
 * in the doc/COPYRIGHT and doc/LICENSE files which should be
 * included in the distribution.
 *
 * Help popup.
 *
 * Opens a new page in the same atk-template style as the
 * atk-application, in a new pop-up screen and shows a help page.
 * input   : $node -> name of the to node for which help is retrieved.
 *
 * This file should only be included from inside the include.php wrapper.
 *
 * @package atk
 * @subpackage utils
 *
 * @author Rene Bakx <rene@ibuildings.nl>
 *
 * @copyright (c)2000-2004 Ibuildings.nl BV
 * @license http://www.achievo.org/atk/licensing ATK Open Source License
 *
 * @version $Revision: 4991 $
 * $Id$
 */
/**
 * @internal include default include file.
 */
include_once($config_atkroot . "atk.php");

SessionManager::atksession();
atksecure();

//  Renders the help screen
$node = $_GET["node"];
$title = Tools::atktext("title_$node", isset($_GET["module"]) ? $_GET["module"] : "");
$helpbase = $config_atkroot;
if (isset($_GET["module"])) {
    $helpbase = Module::moduleDir($_GET["module"]);
}
$file = $helpbase . "help/" . Config::getGlobal('language') . "/help." . $node . ".php";
$data = '<div align="left">';
$data .= implode("<br>", file($file));
$data .= '</div>';

$page = Tools::atknew("atk.ui.atkpage");
$ui = Ui::getInstance();

$output = Output::getInstance();

$page->register_style($ui->stylePath("style.css"));

$res = $ui->renderBox(array(
    "title" => $title,
    "content" => $data
));
$res .= '<br><div align="right"><a href="javascript:window.close();">' . Tools::atktext("close") . '</a></div>';

$page->addContent($res);

$output->output($page->render(Tools::atktext('app_title') . ' - ' . Tools::atktext('help'), true));

$output->outputFlush();