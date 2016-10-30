<?php
// Classes and libraries for module system
//
// webtrees: Web based Family History software
// Copyright (C) 2016 Łukasz Wileński.
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
//
namespace Wooc\WebtreesAddon\WoocSanityCheckModule;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Controller\PageController;
use Fisharebest\Webtrees\Database;
use Fisharebest\Webtrees\Date;
use Fisharebest\Webtrees\Filter;
use Fisharebest\Webtrees\Functions\FunctionsEdit;
use Fisharebest\Webtrees\GedcomTag;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Tree;
use PDO;

class WoocSanityCheckModule extends AbstractModule implements ModuleConfigInterface {

	public function __construct() {
		parent::__construct('wooc_sanity_check');
	}

	// Extend Module
	public function getTitle() {
		return I18N::translate('Wooc Sanity Check');
	}

	// Extend Module
	public function getDescription() {
		return I18N::translate('Checking for data errors that may not be errors, but do seem to be invalid.');
	}

	// Extend Module
	public function modAction($mod_action) {
		switch($mod_action) {
		case 'admin_config':
			$this->config();
			break;
		default:
			http_response_code(404);
			break;
		}
	}

	// Implement Module_Config
	public function getConfigLink() {
		return 'module.php?mod='.$this->getName().'&amp;mod_action=admin_config';
	}

	private function config() {
		global $WT_TREE;
		$controller=new PageController;
		$controller
			->restrictAccess(Auth::isAdmin())
			->setPageTitle(I18N::translate('Sanity check'))
			->pageHeader();
		?>
		<style>
			.alert-red {color:red;padding:15px;}
		</style>
		<ol class="breadcrumb small">
			<li><a href="admin.php"><?php echo I18N::translate('Control panel'); ?></a></li>
			<li><a href="admin_modules.php"><?php echo I18N::translate('Module administration'); ?></a></li>
			<li class="active"><?php echo $controller->getPageTitle(); ?></li>
		</ol>
		<div class="row" style="padding-bottom:10px;">
			<div class="col-sm-4 col-xs-12">
				<form class="form">
					<label for="ged" class="sr-only">
						<?php echo I18N::translate('Family tree'); ?>
					</label>
					<input type="hidden" name="mod" value="<?php echo  $this->getName(); ?>">
					<input type="hidden" name="mod_action" value="admin_config">
					<div class="col-sm-9 col-xs-9" style="padding:0;">
						<?php echo FunctionsEdit::selectEditControl('ged', Tree::getNameList(), null, $WT_TREE->getName(), 'class="form-control"'); ?>
					</div>
					<div class="col-sm-3" style="padding:1;">
						<input type="submit" class="btn btn-primary" value="<?php echo I18N::translate('show'); ?>">
					</div>
				</form>
			</div>
			<div class="col-sm-12 text-right text-left-xs col-xs-12">		
				<?php // TODO: Move to internal item/page
				if (file_exists(WT_MODULES_DIR . $this->getName() . '/readme.html')) { ?>
					<a href="<?php echo WT_MODULES_DIR . $this->getName(); ?>/readme.html" class="btn btn-info">
						<i class="fa fa-newspaper-o"></i>
						<?php echo I18N::translate('ReadMe'); ?>
					</a>
				<?php } ?>
			</div>
			<div class="col-sm-12" style="padding-top:15px;">
				<p class="text-warning"><?php echo I18N::translate('This process can be slow. If you have a large family tree or suspect large numbers of errors you should only select a few checks each time.'); ?></p>
			</div>
		</div>
		<div class="row" style="padding-left:15px;">
			<form method="post" name="configform" action="module.php?mod=<?php echo  $this->getName(); ?>&amp;mod_action=admin_config" class="form">
			<?php echo Filter::getCsrf(); ?>
			<input type="hidden" name="go" value="1">
			<div id="sanity_options" class="form-group">
				<div class="row">
					<span class="col-sm-12 text-info">
						<?php echo I18N::translate('Unlinked records'); ?>
					</span>
					<label class="checkbox-inline col-sm-offset-1">
						<?php echo FunctionsEdit::twoStateCheckbox('unlinked', Filter::post('unlinked')) . ' ' . I18N::translate('Unlinked individuals'); ?>
					</label>
				</div>
				<div class="row">
					<span class="col-sm-12 text-info">
						<?php echo I18N::translate('Date discrepancies'); ?>
					</span>
					<label class="checkbox-inline col-sm-offset-1">
						<?php echo FunctionsEdit::twoStateCheckbox('baptised', Filter::post('baptised')) . ' ' . I18N::translate('Birth after baptism or christening'); ?>
					</label><br>
					<label class="checkbox-inline col-sm-offset-1">
						<?php echo FunctionsEdit::twoStateCheckbox('died', Filter::post('died')) . ' ' . I18N::translate('Birth after death or burial'); ?>
					</label><br>
					<label class="checkbox-inline col-sm-offset-1">
						<?php echo FunctionsEdit::twoStateCheckbox('buri', Filter::post('buri')) . ' ' . I18N::translate('Burial before death'); ?>
					</label>
				</div>
				<div class="row">
					<span class="col-sm-12 text-info">
						<?php echo I18N::translate('Missing data'); ?>
					</span>
					<label class="checkbox-inline col-sm-offset-1">
						<?php echo FunctionsEdit::twoStateCheckbox('sex', Filter::post('sex')) . ' ' . I18N::translate('No gender recorded'); ?>
					</label>
				</div>
				<div class="row">
					<span class="col-sm-12 text-info">
						<?php echo I18N::translate('Duplicated data'); ?>
					</span>
					<label class="checkbox-inline col-sm-offset-1">
						<?php echo FunctionsEdit::twoStateCheckbox('dupe_birt', Filter::post('dupe_birt')) . ' ' . I18N::translate('Birth'); ?>
					</label><br>
					<label class="checkbox-inline col-sm-offset-1">
						<?php echo FunctionsEdit::twoStateCheckbox('dupe_deat', Filter::post('dupe_deat')) . ' ' . I18N::translate('Death'); ?>
					</label><br>
					<label class="checkbox-inline col-sm-offset-1">
						<?php echo FunctionsEdit::twoStateCheckbox('dupe_sex', Filter::post('dupe_sex')) . ' ' . I18N::translate('Gender'); ?>
					</label><br>
					<label class="checkbox-inline col-sm-offset-1">
						<?php echo FunctionsEdit::twoStateCheckbox('dupe_name', Filter::post('dupe_name')) . ' ' . I18N::translate('Name'); ?>
					</label>
				</div>
			</div>
			<div class="row col-sm-9 col-sm-offset-3" style="padding-bottom:15px;">
				<button class="btn btn-primary" type="submit">
					<i class="fa fa-check"></i>
					<?php echo $controller->getPageTitle(); ?>
				</button>
			</div>
			</form>
		</div>
		<?php
		if (!Filter::post('go')) {
			return '';
		}
		if (Filter::post('unlinked')) {
			$data = $this->unlinkedRecords('INDI');
			?>
			<div id="accordion-unlinked" class="panel-group">
				<div id="panel-unlinked" class="panel panel-default">
					<div class="panel-heading">
						<h4 class="panel-title">
							<a href="#collapse" data-target="#collapse" data-toggle="collapse"><?php echo I18N::translate('%s unlinked', I18N::number($data['count'])); ?></a>
						</h4>
					</div>
					<div class="panel-collapse collapse in" id="collapse">
						<div class="panel-body">
							<?php echo $data['html']; ?>
						</div>
					</div>
				</div>
			</div>
			<?php
		}
		if (Filter::post('baptised')) {
			$data = $this->birthComparisons(array('BAPM', 'CHR'));
			?>
			<div id="accordion-baptised" class="panel-group">
				<div id="panel-baptised" class="panel panel-default">
					<div class="panel-heading">
						<h4 class="panel-title">
							<a href="#collapseOne" data-target="#collapseOne" data-toggle="collapse"><?php echo I18N::translate('%s born after baptism or christening', I18N::number($data['count'])); ?></a>
						</h4>
					</div>
					<div class="panel-collapse collapse in" id="collapseOne">
						<div class="panel-body">
							<?php echo $data['html']; ?>
						</div>
					</div>
				</div>
			</div>
			<?php
		}
		if (Filter::post('died')) {
			$data = $this->birthComparisons(array('DEAT', 'BURI'));
			?>
			<div id="accordion-died" class="panel-group">
				<div id="panel-died" class="panel panel-default">
					<div class="panel-heading">
						<h4 class="panel-title">
							<a href="#collapseTwo" data-target="#collapseTwo" data-toggle="collapse"><?php echo I18N::translate('%s born after death or burial', I18N::number($data['count'])); ?></a>
						</h4>
					</div>
					<div class="panel-collapse collapse in" id="collapseTwo">
						<div class="panel-body">
							<?php echo $data['html']; ?>
						</div>
					</div>
				</div>
			</div>
			<?php
		}
		if (Filter::post('buri')) {
			$data = $this->deathComparisons(array('BURI'));
			?>
			<div id="accordion-died" class="panel-group">
				<div id="panel-died" class="panel panel-default">
					<div class="panel-heading">
						<h4 class="panel-title">
							<a href="#collapseTwo" data-target="#collapseTwo" data-toggle="collapse"><?php echo I18N::translate('%s buried before death', I18N::number($data['count'])); ?></a>
						</h4>
					</div>
					<div class="panel-collapse collapse in" id="collapseTwo">
						<div class="panel-body">
							<?php echo $data['html']; ?>
						</div>
					</div>
				</div>
			</div>
			<?php
		}
		if (Filter::post('sex')) {
			$data = $this->missingTag('SEX');
			?>
			<div id="accordion-sex" class="panel-group">
				<div id="panel-sex" class="panel panel-default">
					<div class="panel-heading">
						<h4 class="panel-title">
							<a href="#collapseThree" data-target="#collapseThree" data-toggle="collapse"><?php echo I18N::translate('%s with no gender recorded', I18N::number($data['count'])); ?></a>
						</h4>
					</div>
					<div class="panel-collapse collapse in" id="collapseThree">
						<div class="panel-body">
							<?php echo $data['html']; ?>
						</div>
					</div>
				</div>
			</div>
			<?php
		}
		if (Filter::post('dupe_birt')) {
			$data = $this->duplicateTag('BIRT');
			?>
			<div id="accordion-dupe_birt" class="panel-group">
				<div id="panel-dupe_birt" class="panel panel-default">
					<div class="panel-heading">
						<h4 class="panel-title">
							<a href="#collapseFour" data-target="#collapseFour" data-toggle="collapse"><?php echo I18N::translate('%s with duplicated birth record', I18N::number($data['count'])); ?></a>
						</h4>
					</div>
					<div class="panel-collapse collapse in" id="collapseFour">
						<div class="panel-body">
							<?php echo $data['html']; ?>
						</div>
					</div>
				</div>
			</div>
			<?php
		}
		if (Filter::post('dupe_deat')) {
			$data = $this->duplicateTag('DEAT');
			?>
			<div id="accordion-dupe_deat" class="panel-group">
				<div id="panel-dupe_deat" class="panel panel-default">
					<div class="panel-heading">
						<h4 class="panel-title">
							<a href="#collapseFive" data-target="#collapseFive" data-toggle="collapse"><?php echo I18N::translate('%s with duplicated death record', I18N::number($data['count'])); ?></a>
						</h4>
					</div>
					<div class="panel-collapse collapse in" id="collapseFive">
						<div class="panel-body">
							<?php echo $data['html']; ?>
						</div>
					</div>
				</div>
			</div>
			<?php
		}
		if (Filter::post('dupe_sex')) {
			$data = $this->duplicateTag('SEX');
			?>
			<div id="accordion-dupe_sex" class="panel-group">
				<div id="panel-dupe_sex" class="panel panel-default">
					<div class="panel-heading">
						<h4 class="panel-title">
							<a href="#collapseSix" data-target="#collapseSix" data-toggle="collapse"><?php echo I18N::translate('%s with duplicated gender record', I18N::number($data['count'])); ?></a>
						</h4>
					</div>
					<div class="panel-collapse collapse in" id="collapseSix">
						<div class="panel-body">
							<?php echo $data['html']; ?>
						</div>
					</div>
				</div>
			</div>
			<?php
		}
		if (Filter::post('dupe_name')) {
			$data = $this->identicalName('NAME');
			?>
			<div id="accordion-dupe_sex" class="panel-group">
				<div id="panel-dupe_sex" class="panel panel-default">
					<div class="panel-heading">
						<h4 class="panel-title">
							<a href="#collapseSix" data-target="#collapseSix" data-toggle="collapse"><?php echo I18N::translate('%s with identical name records', I18N::number($data['count'])); ?></a>
						</h4>
					</div>
					<div class="panel-collapse collapse in" id="collapseSix">
						<div class="panel-body">
							<?php echo $data['html']; ?>
						</div>
					</div>
				</div>
			</div>
			<?php
		}
	}
	private function unlinkedRecords($tag) {
		global $WT_TREE;
		$html = '';
		$count = 0;
		$rows = Database::prepare(
			"SELECT i_id AS xref FROM `##individuals` WHERE `i_file` = ? AND `i_gedcom` NOT LIKE '%1 FAM%'"
		)->execute(array($WT_TREE->getTreeId()))->fetchAll(PDO::FETCH_ASSOC);
		foreach ($rows as $row) {
			$person = Individual::getInstance($row->xref, $WT_TREE);
			$html .= '<li><a href="'. $person->getHtmlUrl(). '" target="_blank">'. $person->getFullName(). '</a></li>';
			$count ++;
		}
		return array('html' => $html, 'count' => $count);
	}
	
	private function birthComparisons($tag_array) {
		global $WT_TREE;
		$html = '';
		$count = 0;
		$tag_count = count($tag_array);
		for ($i = 0; $i < $tag_count; $i ++) {
			$rows = Database::prepare(
				"SELECT i_id AS xref, i_gedcom AS gedrec FROM `##individuals` WHERE `i_file` = ? AND `i_gedcom` LIKE CONCAT('%1 ', ?, '%') AND `i_gedcom` NOT LIKE CONCAT('%1 ', ?, ' Y%')"
			)->execute(array($WT_TREE->getTreeId(), $tag_array[$i], $tag_array[$i]))->fetchAll();
			foreach ($rows as $row) {
				$person			= Individual::getInstance($row->xref, $WT_TREE);
				$birth_date 	= $person->getBirthDate();
				$event			= $person->getFirstFact($tag_array[$i]);
				if ($event) {
					$event_date = $event->getDate();
					$age_diff	= Date::Compare($event_date, $birth_date);
					if ($event_date->minimumJulianDay() && $birth_date->minimumJulianDay() && ($age_diff < 0)) {
						$html .= '
							<p>
								<div class="first"><a href="'. $person->getHtmlUrl(). '" target="_blank">'. $person->getFullName(). '</a></div>
								<div class="second"><span class="alert-red">' . GedcomTag::getLabel($tag_array[$i]) . '</span>' . $event_date->Display() . '</div>
								<div class="third"><span class="alert-red">' . GedcomTag::getLabel('BIRT') . '</span>' . $birth_date->Display() . '</div>
							</p>';
						$count ++;
					}
				}
			}
		}
		return array('html' => $html, 'count' => $count);
	}

	private function deathComparisons($tag_array) {
		global $WT_TREE;
		$html = '';
		$count = 0;
		$tag_count = count($tag_array);
		for ($i = 0; $i < $tag_count; $i ++) {
			$rows = Database::prepare(
				"SELECT i_id AS xref, i_gedcom AS gedrec FROM `##individuals` WHERE `i_file` = ? AND `i_gedcom` LIKE CONCAT('%1 ', ?, '%') AND `i_gedcom` NOT LIKE CONCAT('%1 ', ?, ' Y%')"
			)->execute(array($WT_TREE->getTreeId(), $tag_array[$i], $tag_array[$i]))->fetchAll();
			foreach ($rows as $row) {
				$person			= Individual::getInstance($row->xref, $WT_TREE);
				$death_date 	= $person->getDeathDate();
				$event			= $person->getFirstFact($tag_array[$i]);
				if ($event) {
					$event_date = $event->getDate();
					$age_diff	= Date::Compare($event_date, $death_date);
					if ($event_date->minimumJulianDay() && $death_date->minimumJulianDay() && ($age_diff < 0)) {
						$html .= '
							<p>
								<div class="first"><a href="'. $person->getHtmlUrl(). '" target="_blank">'. $person->getFullName(). '</a></div>
								<div class="second"><span class="label">' . GedcomTag::getLabel($tag_array[$i]) . '</span>' . $event_date->Display() . '</div>
								<div class="third"><span class="label">' . GedcomTag::getLabel('DEAT') . '</span>' . $death_date->Display() . '</div>
							</p>';
						$count ++;
					}
				}
			}
		}
		return array('html' => $html, 'count' => $count);
	}

	private function missingTag($tag) {
		global $WT_TREE;
		$html = '';
		$count = 0;
		$rows = Database::prepare(
			"SELECT i_id AS xref, i_gedcom AS gedrec FROM `##individuals` WHERE `i_file` = ? AND `i_gedcom` NOT REGEXP CONCAT('\n[0-9] ' , ?)"
		)->execute(array($WT_TREE->getTreeId(), $tag))->fetchAll();
		foreach ($rows as $row) {
			$person = Individual::getInstance($row->xref, $WT_TREE);
			$html .= '<li><a href="'. $person->getHtmlUrl(). '" target="_blank">'. $person->getFullName(). '</a></li>';
			$count ++;
		}
		return array('html' => $html, 'count' => $count);
	}

	private function duplicateTag($tag) {
		global $WT_TREE;
		$html = '';
		$count = 0;
		$rows = Database::prepare(
			"SELECT i_id AS xref, i_gedcom AS gedrec FROM `##individuals` WHERE `i_file`= ? AND (`i_gedcom` REGEXP '(\n1 " . $tag . ")((.*\n.*)*)(\n1 " . $tag . ")(.*)' OR `i_gedcom` REGEXP '(\n1 " . $tag . ")(.*)(\n1 " . $tag . ")(.*)')"
		)->execute(array($WT_TREE->getTreeId()))->fetchAll();
		foreach ($rows as $row) {
			$person = Individual::getInstance($row->xref, $WT_TREE);
			$html .= '<li><a href="'. $person->getHtmlUrl(). '" target="_blank">'. $person->getFullName(). '</a></li>';
			$count ++;
		}
		return array('html' => $html, 'count' => $count);
	}

	private function identicalName() {
		global $WT_TREE;
		$html = '';
		$count = 0;
		$rows = Database::prepare(
			"SELECT n_id AS xref, COUNT(*) as count  FROM `##name` WHERE `n_file`= ? AND `n_type`= 'NAME' GROUP BY `n_id`, `n_sort`, `n_full` HAVING COUNT(*) > 1 "
		)->execute(array($WT_TREE->getTreeId()))->fetchAll();
		foreach ($rows as $row) {
			$person = Individual::getInstance($row->xref, $WT_TREE);
			$html .= '<li><a href="'. $person->getHtmlUrl(). '" target="_blank">'. $person->getFullName(). '</a></li>';
			$count ++;
		}
		return array('html' => $html, 'count' => $count);
	}
}

return new WoocSanityCheckModule;