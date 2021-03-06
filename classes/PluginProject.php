<?php

/**
 * Models the concept of a plugin. Handles revisions with PluginRelease objects.
 * 
 * @property int $recommended_release_guid GUID of the author-recommended release for this plugin.
 */
class PluginProject extends ElggObject {
	/**
	 * @var PluginRelease
	 */
	private $latest_release;
	
	/**
	 * @var PluginRelease
	 */
	private $recommended_release;
	
	
	protected function initializeAttributes() {
		parent::initializeAttributes();

		$this->attributes['subtype'] = "plugin_project";
	}

	/**
	 * Has the current user has dugg the plugin project
	 * @return bool
	 * @todo Use likes instead?
	 */
	public function isDugg() {
		return !!check_entity_relationship(elgg_get_logged_in_user_guid(), "has_dugg", $this->guid);
	}
	
	public function addDigg() {
		return add_entity_relationship(elgg_get_logged_in_user_guid(), 'has_dugg', $this->guid);
	}
	
	/** @return int */
	public function countDiggs() {
		return $this->countAnnotations('plugin_digg');
	}
	
	/** @return array */	
	public function getScreenshots() {
		return elgg_get_entities_from_relationship(array(
			'relationship_guid' => $this->getGUID(),
			'relationship' => 'image',
			'order_by' => 'guid',
		));
	}
	
	/**
	 * @return PluginRelease The most recently uploaded version of this plugin.
	 */
	public function getLatestRelease() {
		if (isset($this->latest_release)) {
			return $this->latest_release;
		}
		
		$releases = elgg_get_entities(array(
			'type' => 'object',
			'subtype' => 'plugin_release',
			'container_guid' => $this->guid,
			'limit' => 1,
		));
		
		return $this->latest_release = $releases[0];
	}
	
	
	/**
	 * @param string $version The version number to look for (e.g., '1.3.2')
	 * @return PluginRelease The release of this plugin that matches the specified version. 
	 */
	public function getReleaseFromVersion($version) {
		$releases = elgg_get_entities_from_metadata(array(
			'type' => 'object',
			'subtype' => 'plugin_release',
			'container_guid' => $this->guid,
			'metadata_name' => 'version',
			'metadata_value' => $version,
			'limit' => 1,
		));
		
		return $releases[0];
	}
	
	
	/**
	 * @return ElggRelease The author-recommended version of this plugin.
	 *
	 * @todo This probably shouldn't return the latest release by default.
	 * Those are two different concepts.
	 */
	public function getRecommendedRelease() {
		if (isset($this->recommended_release)) {
			return $this->recommended_release;
		}
		
		$release = $this->recommended_release = get_entity($this->recommended_release_guid);
		if ($release) {
			return $release;
		}
		return $this->getLatestRelease();
	}
	
	
	/**
	 * Get a list of releases associated with this project
	 * 
	 * @param array $options
	 * @return array
	 */
	public function getReleases(array $options) {
		return elgg_get_entities(array_merge($options, array(
			'type' => 'object',
			'subtype' => 'plugin_release',
			'container_guid' => $this->guid,
		)));
	}
	
	/**
	 * Increment the download count
	 */
	public function updateDownloadCount() {
		// increment total downloads for all plugins
		$count = (int)elgg_get_plugin_setting('site_plugins_downloads', 'community_plugins');
		elgg_set_plugin_setting('site_plugins_downloads', ++$count, 'community_plugins');

		// increment this plugin project's downloads
		$this->dbUpdateDownloadCount();
	}

	/**
	 * Get the download count for this plugin project
	 * @return int
	 */
	public function getDownloadCount() {
		return $this->dbGetDownloadCount();
	}

	public function saveImage($name, $title, $index) {

		if ($_FILES[$name]['error'] != 0) {
			return FALSE;
		}

		$info = $_FILES[$name];

		// delete original image if exists
		$options = array(
			'relationship_guid' => $this->getGUID(),
			'relationship' => 'image',
			'metadata_name_value_pair' => array('name' => 'project_image', 'value' => "$index")
		);
		if ($old_image = elgg_get_entities_from_relationship($options)) {
			if ($old_image[0] instanceof ElggFile) {
				$old_image[0]->delete();
			}
		}

		$image = new ElggFile();
		$prefix = "plugins/";
		$store_name_base = $prefix . strtolower($this->getGUID() . "_$name");
		$image->title = $title;
		$image->access_id = $this->access_id;
		$image->setFilename($store_name_base . '.jpg');
		$image->setMimetype('image/jpeg');
		$image->originalfilename = $info['name'];
		$image->project_image = $index; // used for deletion on replacement
		$image->save();

		$uf = get_uploaded_file($name);
		if (!$uf) {
			return FALSE;
		}
		$image->open("write");
		$image->write($uf);
		$image->close();

		add_entity_relationship($this->guid, 'image', $image->guid);

		// create a thumbnail
		if ($this->saveThumbnail($image, $store_name_base . '_thumb.jpg') != TRUE) {
			$image->delete();
			return FALSE;
		}

		return TRUE;
	}

	protected function saveThumbnail($image, $name) {
		try {
			$thumbnail = get_resized_image_from_existing_file($image->getFilenameOnFilestore(), 60, 60, true);
		} catch (Exception $e) {
			return FALSE;
		}

		$thumb = new ElggFile();
		$thumb->setMimeType('image/jpeg');
		$thumb->access_id = $this->access_id;
		$thumb->setFilename($name);
		$thumb->open("write");
		$thumb->write($thumbnail);
		$thumb->save();
		$image->thumbnail_guid = $thumb->getGUID();

		if (!$thumb->getGUID()) {
			$thumb->delete();
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Update the number of downloads
	 */
	protected function dbUpdateDownloadCount() {
		$guid = $this->getGUID();
		$db_prefix = get_config('dbprefix');
		$sql = "INSERT INTO {$db_prefix}plugin_downloads
			(guid, downloads) VALUES ($guid, 1)
			ON DUPLICATE KEY UPDATE downloads=downloads+1";
		insert_data($sql);
	}

	/**
	 * Get the number of downloads from the database
	 * 
	 * @return int
	 */
	protected function dbGetDownloadCount() {
		$guid = $this->getGUID();
		$db_prefix = get_config('dbprefix');
		$sql = "SELECT downloads FROM {$db_prefix}plugin_downloads
			WHERE guid = $guid";
		$result = get_data_row($sql);
		if ($result === false) {
			return 0;
		}
		return (int)$result->downloads;
	}

	/**
	 * Get the plugins downloaded the most
	 *
	 * @param array $options Options array for elgg_get_entities()
	 * @return array
	 */
	static public function getPluginsByDownloads(array $options = array()) {
		$db_prefix = get_config('dbprefix');
		
		$defaults = array(
			'type' => 'object',
			'subtype' => 'plugin_project',
			'joins' => array("JOIN {$db_prefix}plugin_downloads pd ON e.guid=pd.guid"),
			'order_by' => 'pd.downloads DESC',
		);
		$options = array_merge($defaults, $options);

		return elgg_get_entities($options);
	}

}
