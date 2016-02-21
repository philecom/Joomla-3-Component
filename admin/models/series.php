<?php
/*--------------------------------------------------------------------------------------------------------|  www.vdm.io  |------/
    __      __       _     _____                 _                                  _     __  __      _   _               _
    \ \    / /      | |   |  __ \               | |                                | |   |  \/  |    | | | |             | |
     \ \  / /_ _ ___| |_  | |  | | _____   _____| | ___  _ __  _ __ ___   ___ _ __ | |_  | \  / | ___| |_| |__   ___   __| |
      \ \/ / _` / __| __| | |  | |/ _ \ \ / / _ \ |/ _ \| '_ \| '_ ` _ \ / _ \ '_ \| __| | |\/| |/ _ \ __| '_ \ / _ \ / _` |
       \  / (_| \__ \ |_  | |__| |  __/\ V /  __/ | (_) | |_) | | | | | |  __/ | | | |_  | |  | |  __/ |_| | | | (_) | (_| |
        \/ \__,_|___/\__| |_____/ \___| \_/ \___|_|\___/| .__/|_| |_| |_|\___|_| |_|\__| |_|  |_|\___|\__|_| |_|\___/ \__,_|
                                                        | |                                                                 
                                                        |_| 				
/-------------------------------------------------------------------------------------------------------------------------------/

	@version		1.3.0
	@build			21st February, 2016
	@created		22nd October, 2015
	@package		Sermon Distributor
	@subpackage		series.php
	@author			Llewellyn van der Merwe <https://www.vdm.io/>	
	@copyright		Copyright (C) 2015. All Rights Reserved
	@license		GNU/GPL Version 2 or later - http://www.gnu.org/licenses/gpl-2.0.html 
	
	A sermon distributor that links to Dropbox. 
                                                             
/-----------------------------------------------------------------------------------------------------------------------------*/

// No direct access to this file
defined('_JEXEC') or die('Restricted access');

use Joomla\Registry\Registry;

// import Joomla modelform library
jimport('joomla.application.component.modeladmin');

/**
 * Sermondistributor Series Model
 */
class SermondistributorModelSeries extends JModelAdmin
{    
	/**
	 * @var        string    The prefix to use with controller messages.
	 * @since   1.6
	 */
	protected $text_prefix = 'COM_SERMONDISTRIBUTOR';
    
	/**
	 * The type alias for this content type.
	 *
	 * @var      string
	 * @since    3.2
	 */
	public $typeAlias = 'com_sermondistributor.series';

	/**
	 * Returns a Table object, always creating it
	 *
	 * @param   type    $type    The table type to instantiate
	 * @param   string  $prefix  A prefix for the table class name. Optional.
	 * @param   array   $config  Configuration array for model. Optional.
	 *
	 * @return  JTable  A database object
	 *
	 * @since   1.6
	 */
	public function getTable($type = 'series', $prefix = 'SermondistributorTable', $config = array())
	{
		return JTable::getInstance($type, $prefix, $config);
	}
    
	/**
	 * Method to get a single record.
	 *
	 * @param   integer  $pk  The id of the primary key.
	 *
	 * @return  mixed  Object on success, false on failure.
	 *
	 * @since   1.6
	 */
	public function getItem($pk = null)
	{
		if ($item = parent::getItem($pk))
		{
			if (!empty($item->params))
			{
				// Convert the params field to an array.
				$registry = new Registry;
				$registry->loadString($item->params);
				$item->params = $registry->toArray();
			}

			if (!empty($item->metadata))
			{
				// Convert the metadata field to an array.
				$registry = new Registry;
				$registry->loadString($item->metadata);
				$item->metadata = $registry->toArray();
			}
			
			if (!empty($item->id))
			{
				$item->tags = new JHelperTags;
				$item->tags->getTagIds($item->id, 'com_sermondistributor.series');
			}
		}
		$this->seriesbfwx = $item->id;

		return $item;
	}

	/**
	* Method to get list data.
	*
	* @return mixed  An array of data items on success, false on failure.
	*/
	public function getSoesermons()
	{
		// [7633] Get the user object.
		$user = JFactory::getUser();
		// [7635] Create a new query object.
		$db = JFactory::getDBO();
		$query = $db->getQuery(true);

		// [7638] Select some fields
		$query->select('a.*');
		$query->select($db->quoteName('c.title','category_title'));

		// [7645] From the sermondistributor_sermon table
		$query->from($db->quoteName('#__sermondistributor_sermon', 'a'));
		$query->join('LEFT', $db->quoteName('#__categories', 'c') . ' ON (' . $db->quoteName('a.catid') . ' = ' . $db->quoteName('c.id') . ')');

		// [8238] From the sermondistributor_preacher table.
		$query->select($db->quoteName('g.name','preacher_name'));
		$query->join('LEFT', $db->quoteName('#__sermondistributor_preacher', 'g') . ' ON (' . $db->quoteName('a.preacher') . ' = ' . $db->quoteName('g.id') . ')');

		// [8238] From the sermondistributor_series table.
		$query->select($db->quoteName('h.name','series_name'));
		$query->join('LEFT', $db->quoteName('#__sermondistributor_series', 'h') . ' ON (' . $db->quoteName('a.series') . ' = ' . $db->quoteName('h.id') . ')');

		// [7661] Filter by seriesbfwx global.
		$seriesbfwx = $this->seriesbfwx;
		if (is_numeric($seriesbfwx ))
		{
			$query->where('a.series = ' . (int) $seriesbfwx );
		}
		elseif (is_string($seriesbfwx))
		{
			$query->where('a.series = ' . $db->quote($seriesbfwx));
		}
		else
		{
			$query->where('a.series = -5');
		}

		// [7678] Join over the asset groups.
		$query->select('ag.title AS access_level');
		$query->join('LEFT', '#__viewlevels AS ag ON ag.id = a.access');
		// [7681] Filter by access level.
		if ($access = $this->getState('filter.access'))
		{
			$query->where('a.access = ' . (int) $access);
		}
		// [7686] Implement View Level Access
		if (!$user->authorise('core.options', 'com_sermondistributor'))
		{
			$groups = implode(',', $user->getAuthorisedViewLevels());
			$query->where('a.access IN (' . $groups . ')');
		}

		// [7693] Order the results by ordering
		$query->order('a.ordering  ASC');

		// [7695] Load the items
		$db->setQuery($query);
		$db->execute();
		if ($db->getNumRows())
		{
			$items = $db->loadObjectList();

			// [11323] set values to display correctly.
			if (SermondistributorHelper::checkArray($items))
			{
				// [11326] get user object.
				$user = JFactory::getUser();
				foreach ($items as $nr => &$item)
				{
					$access = ($user->authorise('sermon.access', 'com_sermondistributor.sermon.' . (int) $item->id) && $user->authorise('sermon.access', 'com_sermondistributor'));
					if (!$access)
					{
						unset($items[$nr]);
						continue;
					}

				}
			}

			// [11589] set selection value to a translatable value
			if (SermondistributorHelper::checkArray($items))
			{
				foreach ($items as $nr => &$item)
				{
					// [11596] convert link_type
					$item->link_type = $this->selectionTranslationSoesermons($item->link_type, 'link_type');
					// [11596] convert source
					$item->source = $this->selectionTranslationSoesermons($item->source, 'source');
				}
			}

			return $items;
		}
		return false;
	}

	/**
	* Method to convert selection values to translatable string.
	*
	* @return translatable string
	*/
	public function selectionTranslationSoesermons($value,$name)
	{
		// [11622] Array of link_type language strings
		if ($name == 'link_type')
		{
			$link_typeArray = array(
				1 => 'COM_SERMONDISTRIBUTOR_SERMON_ENCRYPTED',
				2 => 'COM_SERMONDISTRIBUTOR_SERMON_DIRECT'
			);
			// [11653] Now check if value is found in this array
			if (isset($link_typeArray[$value]) && SermondistributorHelper::checkString($link_typeArray[$value]))
			{
				return $link_typeArray[$value];
			}
		}
		// [11622] Array of source language strings
		if ($name == 'source')
		{
			$sourceArray = array(
				0 => 'COM_SERMONDISTRIBUTOR_SERMON_SELECT_SOURCE',
				1 => 'COM_SERMONDISTRIBUTOR_SERMON_LOCAL_FOLDER',
				2 => 'COM_SERMONDISTRIBUTOR_SERMON_DROPBOX',
				3 => 'COM_SERMONDISTRIBUTOR_SERMON_URL'
			);
			// [11653] Now check if value is found in this array
			if (isset($sourceArray[$value]) && SermondistributorHelper::checkString($sourceArray[$value]))
			{
				return $sourceArray[$value];
			}
		}
		return $value;
	} 

	/**
	 * Method to get the record form.
	 *
	 * @param   array    $data      Data for the form.
	 * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
	 *
	 * @return  mixed  A JForm object on success, false on failure
	 *
	 * @since   1.6
	 */
	public function getForm($data = array(), $loadData = true)
	{		// [10254] Get the form.
		$form = $this->loadForm('com_sermondistributor.series', 'series', array('control' => 'jform', 'load_data' => $loadData));

		if (empty($form))
		{
			return false;
		}

		$jinput = JFactory::getApplication()->input;

		// [10339] The front end calls this model and uses a_id to avoid id clashes so we need to check for that first.
		if ($jinput->get('a_id'))
		{
			$id = $jinput->get('a_id', 0, 'INT');
		}
		// [10344] The back end uses id so we use that the rest of the time and set it to 0 by default.
		else
		{
			$id = $jinput->get('id', 0, 'INT');
		}

		$user = JFactory::getUser();

		// [10350] Check for existing item.
		// [10351] Modify the form based on Edit State access controls.
		if ($id != 0 && (!$user->authorise('series.edit.state', 'com_sermondistributor.series.' . (int) $id))
			|| ($id == 0 && !$user->authorise('series.edit.state', 'com_sermondistributor')))
		{
			// [10364] Disable fields for display.
			$form->setFieldAttribute('ordering', 'disabled', 'true');
			$form->setFieldAttribute('published', 'disabled', 'true');
			// [10367] Disable fields while saving.
			$form->setFieldAttribute('ordering', 'filter', 'unset');
			$form->setFieldAttribute('published', 'filter', 'unset');
		}
		// [10372] If this is a new item insure the greated by is set.
		if (0 == $id)
		{
			// [10375] Set the created_by to this user
			$form->setValue('created_by', null, $user->id);
		}
		// [10378] Modify the form based on Edit Creaded By access controls.
		if ($id != 0 && (!$user->authorise('series.edit.created_by', 'com_sermondistributor.series.' . (int) $id))
			|| ($id == 0 && !$user->authorise('series.edit.created_by', 'com_sermondistributor')))
		{
			// [10390] Disable fields for display.
			$form->setFieldAttribute('created_by', 'disabled', 'true');
			// [10392] Disable fields for display.
			$form->setFieldAttribute('created_by', 'readonly', 'true');
			// [10394] Disable fields while saving.
			$form->setFieldAttribute('created_by', 'filter', 'unset');
		}
		// [10397] Modify the form based on Edit Creaded Date access controls.
		if ($id != 0 && (!$user->authorise('series.edit.created', 'com_sermondistributor.series.' . (int) $id))
			|| ($id == 0 && !$user->authorise('series.edit.created', 'com_sermondistributor')))
		{
			// [10409] Disable fields for display.
			$form->setFieldAttribute('created', 'disabled', 'true');
			// [10411] Disable fields while saving.
			$form->setFieldAttribute('created', 'filter', 'unset');
		}
		// [10444] Only load these values if no id is found
		if (0 == $id)
		{
			// [10447] Set redirected field name
			$redirectedField = $jinput->get('ref', null, 'STRING');
			// [10449] Set redirected field value
			$redirectedValue = $jinput->get('refid', 0, 'INT');
			if (0 != $redirectedValue && $redirectedField)
			{
				// [10453] Now set the local-redirected field default value
				$form->setValue($redirectedField, null, $redirectedValue);
			}
		}

		return $form;
	}

	/**
	 * Method to get the script that have to be included on the form
	 *
	 * @return string	script files
	 */
	public function getScript()
	{
		return 'administrator/components/com_sermondistributor/models/forms/series.js';
	}
    
	/**
	 * Method to test whether a record can be deleted.
	 *
	 * @param   object  $record  A record object.
	 *
	 * @return  boolean  True if allowed to delete the record. Defaults to the permission set in the component.
	 *
	 * @since   1.6
	 */
	protected function canDelete($record)
	{
		if (!empty($record->id))
		{
			if ($record->published != -2)
			{
				return;
			}

			$user = JFactory::getUser();
			// [10575] The record has been set. Check the record permissions.
			return $user->authorise('series.delete', 'com_sermondistributor.series.' . (int) $record->id);
		}
		return false;
	}

	/**
	 * Method to test whether a record can have its state edited.
	 *
	 * @param   object  $record  A record object.
	 *
	 * @return  boolean  True if allowed to change the state of the record. Defaults to the permission set in the component.
	 *
	 * @since   1.6
	 */
	protected function canEditState($record)
	{
		$user = JFactory::getUser();
		$recordId	= (!empty($record->id)) ? $record->id : 0;

		if ($recordId)
		{
			// [10662] The record has been set. Check the record permissions.
			$permission = $user->authorise('series.edit.state', 'com_sermondistributor.series.' . (int) $recordId);
			if (!$permission && !is_null($permission))
			{
				return false;
			}
		}
		// [10679] In the absense of better information, revert to the component permissions.
		return $user->authorise('series.edit.state', 'com_sermondistributor');
	}
    
	/**
	 * Method override to check if you can edit an existing record.
	 *
	 * @param	array	$data	An array of input data.
	 * @param	string	$key	The name of the key for the primary key.
	 *
	 * @return	boolean
	 * @since	2.5
	 */
	protected function allowEdit($data = array(), $key = 'id')
	{
		// [10487] Check specific edit permission then general edit permission.
		$user = JFactory::getUser();

		return $user->authorise('series.edit', 'com_sermondistributor.series.'. ((int) isset($data[$key]) ? $data[$key] : 0)) or $user->authorise('series.edit',  'com_sermondistributor');
	}
    
	/**
	 * Prepare and sanitise the table data prior to saving.
	 *
	 * @param   JTable  $table  A JTable object.
	 *
	 * @return  void
	 *
	 * @since   1.6
	 */
	protected function prepareTable($table)
	{
		$date = JFactory::getDate();
		$user = JFactory::getUser();
		
		if (isset($table->name))
		{
			$table->name = htmlspecialchars_decode($table->name, ENT_QUOTES);
		}
		
		if (isset($table->alias) && empty($table->alias))
		{
			$table->generateAlias();
		}
		
		if (empty($table->id))
		{
			$table->created = $date->toSql();
			// set the user
			if ($table->created_by == 0 || empty($table->created_by))
			{
				$table->created_by = $user->id;
			}
			// Set ordering to the last item if not set
			if (empty($table->ordering))
			{
				$db = JFactory::getDbo();
				$query = $db->getQuery(true)
					->select('MAX(ordering)')
					->from($db->quoteName('#__sermondistributor_series'));
				$db->setQuery($query);
				$max = $db->loadResult();

				$table->ordering = $max + 1;
			}
		}
		else
		{
			$table->modified = $date->toSql();
			$table->modified_by = $user->id;
		}
        
		if (!empty($table->id))
		{
			// Increment the items version number.
			$table->version++;
		}
	}

	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return  mixed  The data for the form.
	 *
	 * @since   1.6
	 */
	protected function loadFormData() 
	{
		// Check the session for previously entered form data.
		$data = JFactory::getApplication()->getUserState('com_sermondistributor.edit.series.data', array());

		if (empty($data))
		{
			$data = $this->getItem();
		}

		return $data;
	} 

	/**
	 * Method to get the unique fields of this table.
	 *
	 * @return  mixed  An array of field names, boolean false if none is set.
	 *
	 * @since   3.0
	 */
	protected function getUniqeFields()
	{
		return false;
	}
	
	/**
	 * Method to delete one or more records.
	 *
	 * @param   array  &$pks  An array of record primary keys.
	 *
	 * @return  boolean  True if successful, false if an error occurs.
	 *
	 * @since   12.2
	 */
	public function delete(&$pks)
	{
		if (!parent::delete($pks))
		{
			return false;
		}
		
		return true;
	}
    
	/**
	 * Method to perform batch operations on an item or a set of items.
	 *
	 * @param   array  $commands  An array of commands to perform.
	 * @param   array  $pks       An array of item ids.
	 * @param   array  $contexts  An array of item contexts.
	 *
	 * @return  boolean  Returns true on success, false on failure.
	 *
	 * @since   12.2
	 */
	public function batch($commands, $pks, $contexts)
	{
		// Sanitize ids.
		$pks = array_unique($pks);
		JArrayHelper::toInteger($pks);

		// Remove any values of zero.
		if (array_search(0, $pks, true))
		{
			unset($pks[array_search(0, $pks, true)]);
		}

		if (empty($pks))
		{
			$this->setError(JText::_('JGLOBAL_NO_ITEM_SELECTED'));
			return false;
		}

		$done = false;

		// Set some needed variables.
		$this->user			= JFactory::getUser();
		$this->table			= $this->getTable();
		$this->tableClassName		= get_class($this->table);
		$this->contentType		= new JUcmType;
		$this->type			= $this->contentType->getTypeByTable($this->tableClassName);
		$this->canDo			= SermondistributorHelper::getActions('series');
		$this->batchSet			= true;

		if (!$this->canDo->get('core.batch'))
		{
			$this->setError(JText::_('JLIB_APPLICATION_ERROR_INSUFFICIENT_BATCH_INFORMATION'));
			return false;
		}
        
		if ($this->type == false)
		{
			$type = new JUcmType;
			$this->type = $type->getTypeByAlias($this->typeAlias);
		}

		$this->tagsObserver = $this->table->getObserverOfClass('JTableObserverTags');

		if (!empty($commands['move_copy']))
		{
			$cmd = JArrayHelper::getValue($commands, 'move_copy', 'c');

			if ($cmd == 'c')
			{
				$result = $this->batchCopy($commands, $pks, $contexts);

				if (is_array($result))
				{
					foreach ($result as $old => $new)
					{
						$contexts[$new] = $contexts[$old];
					}
					$pks = array_values($result);
				}
				else
				{
					return false;
				}
			}
			elseif ($cmd == 'm' && !$this->batchMove($commands, $pks, $contexts))
			{
				return false;
			}

			$done = true;
		}

		if (!$done)
		{
			$this->setError(JText::_('JLIB_APPLICATION_ERROR_INSUFFICIENT_BATCH_INFORMATION'));

			return false;
		}

		// Clear the cache
		$this->cleanCache();

		return true;
	}

	/**
	 * Batch copy items to a new category or current.
	 *
	 * @param   integer  $values    The new values.
	 * @param   array    $pks       An array of row IDs.
	 * @param   array    $contexts  An array of item contexts.
	 *
	 * @return  mixed  An array of new IDs on success, boolean false on failure.
	 *
	 * @since	12.2
	 */
	protected function batchCopy($values, $pks, $contexts)
	{
		if (empty($this->batchSet))
		{
			// [5552] Set some needed variables.
			$this->user 		= JFactory::getUser();
			$this->table 		= $this->getTable();
			$this->tableClassName	= get_class($this->table);
			$this->contentType	= new JUcmType;
			$this->type		= $this->contentType->getTypeByTable($this->tableClassName);
			$this->canDo		= SermondistributorHelper::getActions('series');
		}

		if (!$this->canDo->get('series.create') && !$this->canDo->get('series.batch'))
		{
			return false;
		}

		// [5572] get list of uniqe fields
		$uniqeFields = $this->getUniqeFields();
		// [5574] remove move_copy from array
		unset($values['move_copy']);

		// [5577] make sure published is set
		if (!isset($values['published']))
		{
			$values['published'] = 0;
		}
		elseif (isset($values['published']) && !$this->canDo->get('series.edit.state'))
		{
				$values['published'] = 0;
		}

		$newIds = array();

		// [5614] Parent exists so let's proceed
		while (!empty($pks))
		{
			// [5617] Pop the first ID off the stack
			$pk = array_shift($pks);

			$this->table->reset();

			// [5622] only allow copy if user may edit this item.

			if (!$this->user->authorise('series.edit', $contexts[$pk]))

			{

				// [5632] Not fatal error

				$this->setError(JText::sprintf('JLIB_APPLICATION_ERROR_BATCH_MOVE_ROW_NOT_FOUND', $pk));

				continue;

			}

			// [5637] Check that the row actually exists
			if (!$this->table->load($pk))
			{
				if ($error = $this->table->getError())
				{
					// [5642] Fatal error
					$this->setError($error);

					return false;
				}
				else
				{
					// [5649] Not fatal error
					$this->setError(JText::sprintf('JLIB_APPLICATION_ERROR_BATCH_MOVE_ROW_NOT_FOUND', $pk));
					continue;
				}
			}

			list($this->table->name, $this->table->alias) = $this->_generateNewTitle($this->table->alias, $this->table->name);

			// [5685] insert all set values
			if (SermondistributorHelper::checkArray($values))
			{
				foreach ($values as $key => $value)
				{
					if (strlen($value) > 0 && isset($this->table->$key))
					{
						$this->table->$key = $value;
					}
				}
			}

			// [5697] update all uniqe fields
			if (SermondistributorHelper::checkArray($uniqeFields))
			{
				foreach ($uniqeFields as $uniqeField)
				{
					$this->table->$uniqeField = $this->generateUniqe($uniqeField,$this->table->$uniqeField);
				}
			}

			// [5706] Reset the ID because we are making a copy
			$this->table->id = 0;

			// [5709] TODO: Deal with ordering?
			// [5710] $this->table->ordering	= 1;

			// [5712] Check the row.
			if (!$this->table->check())
			{
				$this->setError($this->table->getError());

				return false;
			}

			if (!empty($this->type))
			{
				$this->createTagsHelper($this->tagsObserver, $this->type, $pk, $this->typeAlias, $this->table);
			}

			// [5725] Store the row.
			if (!$this->table->store())
			{
				$this->setError($this->table->getError());

				return false;
			}

			// [5733] Get the new item ID
			$newId = $this->table->get('id');

			// [5736] Add the new ID to the array
			$newIds[$pk] = $newId;
		}

		// [5740] Clean the cache
		$this->cleanCache();

		return $newIds;
	} 

	/**
	 * Batch move items to a new category
	 *
	 * @param   integer  $value     The new category ID.
	 * @param   array    $pks       An array of row IDs.
	 * @param   array    $contexts  An array of item contexts.
	 *
	 * @return  boolean  True if successful, false otherwise and internal error is set.
	 *
	 * @since	12.2
	 */
	protected function batchMove($values, $pks, $contexts)
	{
		if (empty($this->batchSet))
		{
			// [5354] Set some needed variables.
			$this->user		= JFactory::getUser();
			$this->table		= $this->getTable();
			$this->tableClassName	= get_class($this->table);
			$this->contentType	= new JUcmType;
			$this->type		= $this->contentType->getTypeByTable($this->tableClassName);
			$this->canDo		= SermondistributorHelper::getActions('series');
		}

		if (!$this->canDo->get('series.edit') && !$this->canDo->get('series.batch'))
		{
			$this->setError(JText::_('JLIB_APPLICATION_ERROR_BATCH_CANNOT_EDIT'));
			return false;
		}

		// [5376] make sure published only updates if user has the permission.
		if (isset($values['published']) && !$this->canDo->get('series.edit.state'))
		{
			unset($values['published']);
		}
		// [5389] remove move_copy from array
		unset($values['move_copy']);

		// [5410] Parent exists so we proceed
		foreach ($pks as $pk)
		{
			if (!$this->user->authorise('series.edit', $contexts[$pk]))
			{
				$this->setError(JText::_('JLIB_APPLICATION_ERROR_BATCH_CANNOT_EDIT'));

				return false;
			}

			// [5427] Check that the row actually exists
			if (!$this->table->load($pk))
			{
				if ($error = $this->table->getError())
				{
					// [5432] Fatal error
					$this->setError($error);

					return false;
				}
				else
				{
					// [5439] Not fatal error
					$this->setError(JText::sprintf('JLIB_APPLICATION_ERROR_BATCH_MOVE_ROW_NOT_FOUND', $pk));
					continue;
				}
			}

			// [5445] insert all set values.
			if (SermondistributorHelper::checkArray($values))
			{
				foreach ($values as $key => $value)
				{
					// [5450] Do special action for access.
					if ('access' == $key && strlen($value) > 0)
					{
						$this->table->$key = $value;
					}
					elseif (strlen($value) > 0 && isset($this->table->$key))
					{
						$this->table->$key = $value;
					}
				}
			}


			// [5462] Check the row.
			if (!$this->table->check())
			{
				$this->setError($this->table->getError());

				return false;
			}

			if (!empty($this->type))
			{
				$this->createTagsHelper($this->tagsObserver, $this->type, $pk, $this->typeAlias, $this->table);
			}

			// [5475] Store the row.
			if (!$this->table->store())
			{
				$this->setError($this->table->getError());

				return false;
			}
		}

		// [5484] Clean the cache
		$this->cleanCache();

		return true;
	}
	
	/**
	 * Method to save the form data.
	 *
	 * @param   array  $data  The form data.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   1.6
	 */
	public function save($data)
	{
		$input	= JFactory::getApplication()->input;
		$filter	= JFilterInput::getInstance();
        
		// set the metadata to the Item Data
		if (isset($data['metadata']) && isset($data['metadata']['author']))
		{
			$data['metadata']['author'] = $filter->clean($data['metadata']['author'], 'TRIM');
            
			$metadata = new JRegistry;
			$metadata->loadArray($data['metadata']);
			$data['metadata'] = (string) $metadata;
		} 
        
		// Set the Params Items to data
		if (isset($data['params']) && is_array($data['params']))
		{
			$params = new JRegistry;
			$params->loadArray($data['params']);
			$data['params'] = (string) $params;
		}

		// [5766] Alter the name for save as copy
		if ($input->get('task') == 'save2copy')
		{
			$origTable = clone $this->getTable();
			$origTable->load($input->getInt('id'));

			if ($data['name'] == $origTable->name)
			{
				list($name, $alias) = $this->_generateNewTitle($data['alias'], $data['name']);
				$data['name'] = $name;
				$data['alias'] = $alias;
			}
			else
			{
				if ($data['alias'] == $origTable->alias)
				{
					$data['alias'] = '';
				}
			}

			$data['published'] = 0;
		}

		// [5793] Automatic handling of alias for empty fields
		if (in_array($input->get('task'), array('apply', 'save', 'save2new')) && (int) $input->get('id') == 0)
		{
			if ($data['alias'] == null)
			{
				if (JFactory::getConfig()->get('unicodeslugs') == 1)
				{
					$data['alias'] = JFilterOutput::stringURLUnicodeSlug($data['name']);
				}
				else
				{
					$data['alias'] = JFilterOutput::stringURLSafe($data['name']);
				}

				$table = JTable::getInstance('series', 'sermondistributorTable');

				if ($table->load(array('alias' => $data['alias'])) && ($table->id != $data['id'] || $data['id'] == 0))
				{
					$msg = JText::_('COM_SERMONDISTRIBUTOR_SERIES_SAVE_WARNING');
				}

				list($name, $alias) = $this->_generateNewTitle($data['alias'], $data['name']);
				$data['alias'] = $alias;

				if (isset($msg))
				{
					JFactory::getApplication()->enqueueMessage($msg, 'warning');
				}
			}
		}

		// [5832] Alter the uniqe field for save as copy
		if ($input->get('task') == 'save2copy')
		{
			// [5835] Automatic handling of other uniqe fields
			$uniqeFields = $this->getUniqeFields();
			if (SermondistributorHelper::checkArray($uniqeFields))
			{
				foreach ($uniqeFields as $uniqeField)
				{
					$data[$uniqeField] = $this->generateUniqe($uniqeField,$data[$uniqeField]);
				}
			}
		}
		
		if (parent::save($data))
		{
			return true;
		}
		return false;
	}
	
	/**
	 * Method to generate a uniqe value.
	 *
	 * @param   string  $field name.
	 * @param   string  $value data.
	 *
	 * @return  string  New value.
	 *
	 * @since   3.0
	 */
	protected function generateUniqe($field,$value)
	{

		// set field value uniqe 
		$table = $this->getTable();

		while ($table->load(array($field => $value)))
		{
			$value = JString::increment($value);
		}

		return $value;
	}

	/**
	* Method to change the title & alias.
	*
	* @param   string   $alias        The alias.
	* @param   string   $title        The title.
	*
	* @return	array  Contains the modified title and alias.
	*
	*/
	protected function _generateNewTitle($alias, $title)
	{

		// [5866] Alter the title & alias
		$table = $this->getTable();

		while ($table->load(array('alias' => $alias)))
		{
			$title = JString::increment($title);
			$alias = JString::increment($alias, 'dash');
		}

		return array($title, $alias);
	}
}
