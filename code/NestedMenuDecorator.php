<?php

/**
 * A NestedMenu Decorator which provides the ability to add nested <ul> SiteTree menus into
 * your Silverstripe 2.4.x templates.
 *
 * By default, the containing module will add the methods NestedMenu and HasNestedMenu to
 * the SiteTree class and all subclasses. This decorator also adds a checkbox field
 * (ShowChildrenInMenus) to the CMS admin interface to allow CMS users to hide all of the
 * sub-pages of a page from the nested menu.
 * 
 * Basic usage in your template:
 *
 * <code>$NestedMenu</code>
 *
 * Which will output HTML in the form:
 *
 * <code>
 * 	<ul class="nested-menu nested-menu-level-1 nested-menu-nesting-1">
 * 	  <li class="first link"><a class="first link" href="/">Home</a></li>
 * 	  <li class="current open"><a class="current open" href="/about-us/">About Us</a>
 * 	    <ul class="nested-menu-level-2 nested-menu-nesting-2"><li class="first link">
 * 	      <li class="first link"><a class="first link" href="/about-us/our-staff/">Our Staff</a></li>
 * 	      <li class="last link"><a class="last link" href="/about-us/another-page/">Another Page</a></li>
 * 	    </ul>
 * 	  </li>
 * 	  <li class="last link"><a class="last link" href="/contact-us/">Contact Us</a></li>
 * 	</ul>
 * </code>
 * 
 * The HTML puts classes on both the <li> and a <a> to aid styling.
 * The nested-menu class will always be put on ONLY the outer <ul>
 *
 * You can also start at a sub level (good for when you have a horizontal top-level nav) by
 * calling NestedMenu with a parameter indicating the level to start at:
 * 
 *	<code>$NestedMenu(2)</code>
 *
 * You can limit the maximum depth of the nesting by specifying a second parameter:
 *
 *	<code>$NestedMenu(1,3)</code>
 *
 * You can also use HasNestedMenu to include additional markup if a menu is available, e.g:
 *
 * <code>
 *	<% if HasNestedMenu(2) %>
 *	  <nav id="secondary-navigation">
 *	    <h4>In this section&hellip;</h4>
 *	    $NestedMenu(2)
 *	  </nav>
 *	<% end_if %>
 * </code>
 *
 * @package		nestedmenu
 * @author		Mark James <mail@mark.james.name>
 * @copyright	2011 - Mark James
 * @license		New BSD License
 * @link		http://github.com/markjames/silverstripe-nestedmenu
 * 
 * Copyright (c) 2011, Mark James
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 * 
 *     * Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 * 
 *     * Redistributions in binary form must reproduce the above copyright notice,
 *       this list of conditions and the following disclaimer in the documentation
 *       and/or other materials provided with the distribution.
 * 
 *     * Neither the name of Zend Technologies USA, Inc. nor the names of its
 *       contributors may be used to endorse or promote products derived from this
 *       software without specific prior written permission.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */
class NestedMenuDecorator extends SiteTreeDecorator {

	/**
	 * The format for a single item within the nested menu.
	 * Override this in your config if you need to alter the markup
	 * (e.g. add a span around the title)
	 *
	 * The following directives are used when building the menu:
	 * - %1$s The page's title
	 * - %2$s The page link
	 * - %3$s The classes added to the list item (and also head anchor)
	 * - %4$s The code (nested ULs) for any nested sub-menus
	 * 
	 * @var string
	 */
	public static $list_item_format = '<li class="%3$s"><a class="%3$s" href="%2$s">%1$s</a>%4$s</li>';

	/**
	 * List of classes to hide from the sub nav
	 *
	 * @var array
	 **/
	public static $classnames_to_exclude_children = array("StackedListPage");

	/**
	 * Define an extra database field for showing children in nested menus
	 *
	 * @return array Returns a map where the keys are db, has_one, etc, and
	 *               the values are additional fields/relations to be defined.
	 */
	function extraStatics() {
		return array(
			'db' => array(
				'ShowChildrenInMenus' => 'Boolean',
			),
			'defaults' => array(
				'ShowChildrenInMenus' => true,
			)
		);
	}

	/**
	 * Add in a CMS-editable field for choosing if the child pages of the current page
	 * should show up in nested menus
	 *
	 * @param FieldSet $fields FieldSet with a contained TabSet
	 */
	function updateCMSFields(&$fields) {

		$fields->insertAfter(
			new Checkboxfield(
				'ShowChildrenInMenus',
				_t('NestedMenuDecorator.SHOWCHILDRENINMENUS', "Show children in menus?")
			),
			'ShowInMenus'
		);

	}

	/**
	 * Set up the default values for ShowChildrenInMenus for when
	 * dev/build is run.
	 *
	 * @todo Rework check so that if a CMS user unchecks ShowChildrenInMenus on _all_ pages 
	 *       that running this does not re-check it for all pages (consult page versions?)
	 */
	public function requireDefaultRecords() {
		parent::requireDefaultRecords();

		// If all of the pages that this item extends have their ShowChildrenInMenus set to 0, then
		// we have _probably_ just added the field, so we should se them all to the default.
		$class = $this->ownerBaseClass;

		if (!DataObject::get_one($class, '"ShowChildrenInMenus" != 0',false)) {
			DB::alteration_message(
				$class.' - ShowChildrenInMenus: Setting to 1 for all pages', 'created'
			);

			// TODO: Is there a better option than raw SQL?
			DB::getConn()->query("UPDATE \"{$class}_Live\" SET \"ShowChildrenInMenus\" = 1");
			DB::getConn()->query("UPDATE \"{$class}\" SET \"ShowChildrenInMenus\" = 1");

		} 

	}

	/**
	 * Determines if a menu is available when calling {@link NestedMenuDecorator->NestedMenu()}
	 * for a given level of the sitetree heirarchy.
	 *
	 * This method is useful to wrap in an if around any additional markup which you only want to
	 * display if a menu exists (such a <nav> element, or an <h2>In this section...</h2> heading)
	 *
	 * @param int The level of the sitetree to start the menu at (Where 1 is the top level)
	 * @return boolean TRUE if a menu will be displayed, FALSE otherwise
	 */
	public function HasNestedMenu($level = 1) {

		$pages = $this->filterVisiblePages( $this->getPagesForLevel($level) );
		return !!count($pages);

	}

	/**
	 * Generates the HTML for a nested menu for a given level of the sitetree heirarchy.
	 *
	 * @param int The level of the sitetree to start the menu at (Where 1 is the top level)
	 * @param int An optional maximum depth of recursion for the menu (starting at whichever
	 *            level was specified in the first parameter)
	 * @return string
	 */
	public function NestedMenu($level=1, $maxDepth=null) {

		$siteTreeLevel = $level;

		$pages = $this->getPagesForLevel($level);
		return $this->generateListForDataObjectSet( $pages, $level, 1, $maxDepth );

	}

	/**
	 * Generate a UL containing a list item for each item in a given set of pages, and recursing
	 * down the structure (generating ULs on the way) for the current section.
	 *
	 * This method does all of the work turning a list of pages into a nested <ul>
	 *
	 * @param DataObjectSet The {@link DataObjectSet} of pages to include in the list at this level
	 * @param int The level of the sitetree that this set represents
	 * @param int The recursion depth (might be different to the sitetree level if the menu did
	 *            not start from the top level pages)
	 * @param int The maximum number of levels to recurse through
	 * @return String
	 */
	protected function generateListForDataObjectSet($set, $siteTreeLevel = 1, $nestingLevel = 1, $maxDepthLevel = null) {

		// Open the <ul> (stick an extra 'nested-menu' class on if it is top-level)
		if (1 == $nestingLevel) {
			$out = '<ul class="nested-menu nested-menu-level-' . $siteTreeLevel
			     . ' nested-menu-nesting-' . $nestingLevel . '">';
		} else {
			$out = '<ul class="nested-menu-level-' . $siteTreeLevel
			     . ' nested-menu-nesting-' . $nestingLevel . '">';
		}

		// For each page
		foreach($set as $index => $page) {
			$classes = array();

			if( in_array($page->Parent->ClassName, self::$classnames_to_exclude_children) ) {
				continue;
			}
			
			
			// Get classes to put on li and a
			$classes []= $page->FirstLast();
			$classes []= $page->LinkingMode();

			$ul = '';

			// If:
			// * This page is allowed to display it's children in a menu (ShowChildrenInMenus)
			// * Is the current page, or is an ancestor to the current page
			// * We have not exceeded the maximum depth level that was specified
			if ($page->ShowChildrenInMenus 
				&& $page->isSection()
				&& ($maxDepthLevel === null || $nestingLevel < $maxDepthLevel)
			) {
				
				// Load the children (but only ones with ShowInMenus set)
				$children = $page->Children();

				// Hide pages which the current user cannot view for other reasons (i.e. permissions)
				$visible = $this->filterVisiblePages( $children );

				// If there are any left, drop a class onto the li and recurse down
				if (count($visible)) {
					$classes []= 'open';
					$children = $visible;
					$ul = $this->generateListForDataObjectSet(
						$children,
						$siteTreeLevel+1,
						$nestingLevel+1,
						$maxDepthLevel
					);
				}

			}

			// We have everything now, build up the list item using the defined string format
			$out .= sprintf(
				self::$list_item_format,
				Convert::raw2xml($page->MenuTitle),
				Convert::raw2att($page->Link()),
				trim(implode(' ',$classes)),
				$ul
			);

		}

		// Close the <ul>
		$out .= '</ul>';

		return $out;

	}

	/**
	 * Filters a {@link DataObjectSet} of pages, returning a new set
	 * containing only the pages which should appear in the menu.
	 * Extend this to manipulate the DataObjectSet to remove or add
	 * additional entries
	 *
	 * @param DataObjectSet The {@link DataObjectSet} of pages to filter
	 * @return DataObjectSet
	 */
	protected function filterVisiblePages($set) {

		return $set;

	}

	/**
	 * Returns a fixed navigation menu of the given level.
	 *
	 * @param int The level of the the site tree to get
	 * @return DataObjectSet
	 */
	protected function getPagesForLevel($level = 1) {
		if($level == 1) {
			$result = DataObject::get("SiteTree", "\"ShowInMenus\" = 1 AND \"ParentID\" = 0");

		} else {
			$parent = $this->owner->data();
			$stack = array($parent);
			
			if($parent) {
				while($parent = $parent->Parent) {
					array_unshift($stack, $parent);
				}
			}
			
			if(isset($stack[$level-2])) $result = $stack[$level-2]->Children();
		}

		return $this->filterVisiblePages( $result );
	}

}