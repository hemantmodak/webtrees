<?php
// Merge Two Gedcom Records
//
// This page will allow you to merge 2 gedcom records
//
// webtrees: Web based Family History software
// Copyright (C) 2013 webtrees development team.
//
// Derived from PhpGedView
// Copyright (C) 2002 to 2010 PGV Development Team.  All rights reserved.
//
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

define('WT_SCRIPT_NAME', 'admin_site_merge.php');
require './includes/session.php';

$controller=new WT_Controller_Page;
$controller
	->requireManagerLogin()
	->setPageTitle(WT_I18N::translate('Merge records'))
	->pageHeader();

require_once WT_ROOT.'includes/functions/functions_edit.php';
require_once WT_ROOT.'includes/functions/functions_import.php';

$ged=$GEDCOM;
$gid1   = WT_Filter::post('gid1', WT_REGEX_XREF);
$gid2   = WT_Filter::post('gid2', WT_REGEX_XREF);
$action = WT_Filter::post('action', 'choose|select|merge', 'choose');
$ged2   = WT_Filter::post('ged2', '.+', $ged);
$keep1  = WT_Filter::postArray('keep1');
$keep2  = WT_Filter::postArray('keep2');

if (count(WT_Tree::getAll())==1) { //Removed becasue it doesn't work here for multiple GEDCOMs. Can be reinstated when fixed (https://bugs.launchpad.net/webtrees/+bug/613235)
	$controller->addExternalJavascript(WT_STATIC_URL.'js/autocomplete.js');
}

if ($action!='choose') {
	if ($gid1==$gid2 && $GEDCOM==$ged2) {
		$action='choose';
		echo '<span class="error">', WT_I18N::translate('You entered the same IDs.  You cannot merge the same records.'), '</span>';
	} else {
		$rec1 = WT_GedcomRecord::getInstance($gid1);
		$rec2 = WT_GedcomRecord::getInstance($gid2);

		if (!$rec1) {
			echo '<span class="error">', WT_I18N::translate('Unable to find record with ID'), ':</span> ', $rec1->getXref(), ', ', $ged;
			$action='choose';
		} elseif (!$rec2) {
			echo '<span class="error">', WT_I18N::translate('Unable to find record with ID'), ':</span> ', $rec2->getXref(), ', ', $ged2;
			$action='choose';
		} elseif ($rec1::RECORD_TYPE != $rec2::RECORD_TYPE) {
				echo '<span class="error">', WT_I18N::translate('Records are not the same type.  Cannot merge records that are not the same type.'), '</span>';
				$action='choose';
		} else {
			$facts1 = array();
			$facts2 = array();
			foreach ($rec1->getFacts() as $fact) {
				$facts1[$fact->getFactId()]=$fact;
			}
			foreach ($rec2->getFacts() as $fact) {
				$facts2[$fact->getFactId()]=$fact;
			}
			if ($action=='select') {
				echo '<div id="merge2"><h3>', WT_I18N::translate('Merge records'), '</h3>';
				echo '<form method="post" action="admin_site_merge.php">';
				echo WT_I18N::translate('The following facts were exactly the same in both records and will be merged automatically.'), '<br>';
				echo '<input type="hidden" name="gid1" value="', $rec1->getXref(), '">';
				echo '<input type="hidden" name="gid2" value="', $rec2->getXref(), '">';
				echo '<input type="hidden" name="ged" value="', $GEDCOM, '">';
				echo '<input type="hidden" name="ged2" value="', $ged2, '">';
				echo '<input type="hidden" name="action" value="merge">';
				$skip = array();
				echo '<table>';
				foreach ($facts1 as $fact_id1 => $fact1) {
					foreach ($facts2 as $fact_id2 => $fact2) {
						if ($fact_id1 == $fact_id2) {
							echo '<tr><td><input type="checkbox" name="keep1[]" value="', $fact_id1, '" checked="checked"></td><td>', nl2br($fact1->getGedcom(), false), '</td></tr>';
							$skip[] = $fact_id1;
							unset($facts1[$fact_id1]);
							unset($facts2[$fact_id2]);
						}
					}
				}
				if (!$skip) {
					echo '<tr><td>', WT_I18N::translate('No matching facts found'), '</td></tr>';
				}
				echo '</table><br>';
				echo WT_I18N::translate('The following facts did not match.  Select the information you would like to keep.');
				echo '<table>';
				echo '<tr><th>', WT_I18N::translate('Record'), ' ', $rec1->getXref(), '</th><th>', WT_I18N::translate('Record'), ' ', $rec2->getXref(), '</th></tr>';
				echo '<tr><td>';
				echo '<table>';
				foreach ($facts1 as $i=>$fact1) {
					if ($fact1->getTag() != 'CHAN') {
						echo '<tr><td><input type="checkbox" name="keep1[]" value="', $i, '" checked="checked"></td>';
						echo '<td>', nl2br($fact1->getGedcom(), false), '</td></tr>';
					}
				}
				echo '</table>';
				echo '</td><td>';
				echo '<table>';
				foreach ($facts2 as $j=>$fact2) {
					if ($fact2->getTag() != 'CHAN') {
						echo '<tr><td><input type="checkbox" name="keep2[]" value="', $j, '" checked="checked"></td>';
						echo '<td>', nl2br($fact2->getGedcom(), false), '</td></tr>';
					}
				}
				echo '</table>';
				echo '</td></tr>';
				echo '</table>';
				echo '<input type="submit" value="', WT_I18N::translate('save'), '">';
				echo '</form></div>';
			} elseif ($action=='merge') {
				$manual_save = true;
				echo '<div id="merge3"><h3>', WT_I18N::translate('Merge records'), '</h3>';
				if ($GEDCOM==$ged2) {
					//-- replace all the records that linked to gid2
					$ids=fetch_all_links($gid2, WT_GED_ID);
					foreach ($ids as $id) {
						$record=WT_GedcomRecord::getInstance($id);
						echo WT_I18N::translate('Updating linked record'), ' ', $id, '<br>';
						$gedcom=str_replace("@$gid2@", "@$gid1@", $record->getGedcom());
						$gedcom=preg_replace(
							'/(\n1.*@.+@.*(?:(?:\n[2-9].*)*))((?:\n1.*(?:\n[2-9].*)*)*\1)/',
							'$2',
							$gedcom
						);
						$record->updateRecord($gedcom, true);
					}
					// Update any linked user-accounts
					WT_DB::prepare(
						"UPDATE `##user_gedcom_setting`".
						" SET setting_value=?".
						" WHERE gedcom_id=? AND setting_name='gedcomid' AND setting_value=?"
					)->execute(array($gid2, WT_GED_ID, $gid1));

					// Merge hit counters
					$hits=WT_DB::prepare(
						"SELECT page_name, SUM(page_count)".
						" FROM `##hit_counter`".
						" WHERE gedcom_id=? AND page_parameter IN (?, ?)".
						" GROUP BY page_name"
					)->execute(array(WT_GED_ID, $gid1, $gid2))->fetchAssoc();
					foreach ($hits as $page_name=>$page_count) {
						WT_DB::prepare(
							"UPDATE `##hit_counter` SET page_count=?".
							" WHERE gedcom_id=? AND page_name=? AND page_parameter=?"
						)->execute(array($page_count, WT_GED_ID, $page_name, $gid1));
					}
					WT_DB::prepare(
						"DELETE FROM `##hit_counter`".
						" WHERE gedcom_id=? AND page_parameter=?"
					)->execute(array(WT_GED_ID, $gid2));
				}
				$gedcom = "0 @" . $rec1->getXref() . "@ " . $rec1::RECORD_TYPE;
				foreach ($facts1 as $fact_id=>$fact) {
					if (in_array($fact_id, $keep1)) {
						$gedcom .= "\n" . $fact->getGedcom();
						echo WT_I18N::translate('Adding'), " ", $fact->getTag(), ' ', WT_I18N::translate('from'), ' ', $rec1->getXref(), '<br>';
					}
				}
				foreach ($facts2 as $fact_id=>$fact) {
					if (in_array($fact_id, $keep2)) {
						$gedcom .= "\n" . $fact->getGedcom();
						echo WT_I18N::translate('Adding'), " ", $fact->getTag(), ' ', WT_I18N::translate('from'), ' ', $rec2->getXref(), '<br>';
					}
				}

				$rec1->updateRecord($gedcom, true);
				$rec2->deleteRecord();
				echo WT_I18N::translate('GEDCOM record successfully deleted.'), '<br>';
				echo
					'<p>',
						WT_I18N::translate(
							'Record %s successfully updated.',
							'<a href="'.$rec1->getHtmlUrl().'">'.$rec1->getXref().'</a>'
						),
					'</p';
				$fav_count=update_favorites($gid2, $gid1);
				if ($fav_count > 0) {
					echo '<p>', $fav_count, ' ', WT_I18N::translate('favorites updated.'), '<p>';
				}
				echo '</div>';
			}
		}
	}
}
if ($action=='choose') {
	$controller->addInlineJavascript('
	var pasteto;
	function iopen_find(textbox, gedselect) {
		pasteto = textbox;
		ged = gedselect.options[gedselect.selectedIndex].value;
		findwin = window.open("find.php?type=indi&ged="+ged, "_blank", find_window_specs);
	}
	function fopen_find(textbox, gedselect) {
		pasteto = textbox;
		ged = gedselect.options[gedselect.selectedIndex].value;
		findwin = window.open("find.php?type=fam&ged="+ged, "_blank", find_window_specs);
	}
	function sopen_find(textbox, gedselect) {
		pasteto = textbox;
		ged = gedselect.options[gedselect.selectedIndex].value;
		findwin = window.open("find.php?type=source&ged="+ged, "_blank", find_window_specs);
	}
	function paste_id(value) {
		pasteto.value=value;
	}
	');

	echo 
		'<div id="merge"><h3>', WT_I18N::translate('Merge records'), '</h3>
		<form method="post" name="merge" action="admin_site_merge.php">
		<input type="hidden" name="action" value="select">
		<p>', WT_I18N::translate('Select two GEDCOM records to merge.  The records must be of the same type.'), '</p>
		<table><tr>
		<td>',
		WT_I18N::translate('Merge to ID:'),
		'</td><td>
		<input type="text" name="gid1" id="gid1" value="', $gid1, '" size="10" tabindex="1" autofocus="autofocus">
		<select name="ged" tabindex="4"';
	if (count(WT_Tree::getAll())==1) {
		echo 'style="width:1px;visibility:hidden;"';
	}
	echo ' >';
	foreach (WT_Tree::getAll() as $tree) {
		echo '<option value="', $tree->tree_name_html, '"';
		if (empty($ged) && $tree->tree_id==WT_GED_ID || !empty($ged) && $ged==$tree->tree_name) {
			echo ' selected="selected"';
		}
		echo ' dir="auto">', $tree->tree_title_html, '</option>';
	}
	echo
		'</select>
		<a href="#" onclick="iopen_find(document.merge.gid1, document.merge.ged);" tabindex="6" class="icon-button_indi" title="'.WT_I18N::translate('Find an individual').'"></a>
		<a href="#" onclick="fopen_find(document.merge.gid1, document.merge.ged);" tabindex="8" class="icon-button_family" title="'.WT_I18N::translate('Find a family').'"></a>
		<a href="#" onclick="sopen_find(document.merge.gid1, document.merge.ged);" tabindex="10" class="icon-button_source" title="'.WT_I18N::translate('Find a source').'"></a>
		</td></tr><tr><td>',
		WT_I18N::translate('Merge from ID:'),
		'</td><td>
		<input type="text" name="gid2" id="gid2" value="', $gid2, '" size="10" tabindex="2">&nbsp;',
		'<select name="ged2" tabindex="5"';
	if (count(WT_Tree::getAll())==1) {
		echo 'style="width:1px;visibility:hidden;"';
	}
	echo ' >';
	foreach (WT_Tree::getAll() as $tree) {
		echo '<option value="', $tree->tree_name_html, '"';
		if (empty($ged2) && $tree->tree_id==WT_GED_ID || !empty($ged2) && $ged2==$tree->tree_name) {
			echo ' selected="selected"';
		}
		echo ' dir="auto">', $tree->tree_title_html, '</option>';
	}
	echo
		'</select>
		<a href="#" onclick="iopen_find(document.merge.gid2, document.merge.ged2);" tabindex="7" class="icon-button_indi" title="'.WT_I18N::translate('Find an individual').'"></a>
		<a href="#" onclick="fopen_find(document.merge.gid2, document.merge.ged2);" tabindex="9" class="icon-button_family" title="'.WT_I18N::translate('Find a family').'"></a>
		<a href="#" onclick="sopen_find(document.merge.gid2, document.merge.ged2);" tabindex="11" class="icon-button_source" title="'.WT_I18N::translate('Find a source').'"></a>
		</td></tr></table>
		<input type="submit" value="', WT_I18N::translate('next'), '" tabindex="3">
		</form></div>';
}
