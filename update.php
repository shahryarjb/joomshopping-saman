<?php
/**
 * @package     Joomla - > Site and Administrator payment info
 * @subpackage  com_Jshopping
 * @subpackage 	trangell_Saman
 * @copyright   trangell team => https://trangell.com
 * @copyright   Copyright (C) 20016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
// No direct access to this file
defined('_JEXEC') or die('Restricted access');

$db = JFactory::getDbo();
$query = $db->getQuery(true);
$columns = array('name_en-GB', 'name_de-DE', 'description_en-GB', 'description_de-DE', 'payment_code', 'payment_class', 'scriptname', 'payment_publish', 'payment_ordering', 'payment_params', 'payment_type', 'tax_id', 'price', 'show_descr_in_email','name_fa-IR');
$values = array($db->q('TrangellSaman'), $db->q('TrangellSaman'), $db->q(''), $db->q(''), $db->q('trangellsaman'), $db->q('pm_trangellsaman'), $db->q('pm_trangellsaman'), $db->q(0), $db->q(2), $db->q('merchant_id='), $db->q(2), $db->q(1), $db->q(0), $db->q(0),$db->q('TrangellSaman'));
$query->insert($db->qn('#__jshopping_payment_method'));
$query->columns($db->qn($columns));
$query->values(implode(',', $values));
$db->setQuery((string)$query); 
$db->execute();

?>
