<?php
/**
 * Elgg plugin project object view.
 * Four views:
 * full
 * search listing
 * front page listing
 * widget listing
 */

$project = $vars['entity'];
if (!$project) {
	return ' ';
}

// get the recommend release or latest
$release = get_entity(get_input('release', $project->recommended_release_guid));
if (!$release || !($release instanceof FilePluginFile)) {
	$releases = elgg_get_entities(array('container_guid' => $project->getGUID()));
	if ($releases) {
		$release = $releases[0];
	}
}

//set required variables
$project_guid = $project->getGUID();
$project_owner = get_entity($project->owner_guid);
$tags = $project->tags;
$title = $project->title;
$desc = $project->description;
$summary = $project->summary;
$license = $project->license;
$friendlytime = friendly_time($project->time_created);
$downloads = (int)get_annotations_sum($project_guid, '', '', 'download');
$diggs = count_annotations($project_guid, "object", "plugin_project", "plugin_digg");
$usericon = elgg_view("profile/icon", array('entity' => $project_owner,
											'size' => 'small',
											)
						);


switch(get_context()) {
	case 'search':
		$info = "<span class='downloadsnumber'>{$downloads}</span>";
		$info .= "<p class='pluginName'> <a href=\"{$project->getURL()}\">{$title} </a></p>";
		if ($summary) {
			$info .= "<p class='description'>" . $summary . "</p>";
		}
		$user_url = "{$vars['url']}pg/plugins/{$project_owner->username}";
		$info .= "<p class=\"owner_timestamp\"><a href=\"$user_url\">{$project_owner->name}</a> {$friendlytime}</p>";
		echo elgg_view_listing($usericon, $info);
		break;
		
	case 'plugins':
?>
<div class="pluginsrepo_file">
	<div class="pluginsrepo_title_owner_wrapper">
		<div class="pluginsrepo_user_gallery_link">
			<a href="<?php echo $vars['url']; ?>pg/plugins/all">back to plugins</a>
		</div>
		<div class="pluginsrepo_title">
			<h2><a href="<?php echo $project->getURL(); ?>"><?php echo $title; ?></a></h2>
		</div>
		<div class="pluginsrepo_owner">
			<?php echo elgg_view("profile/icon", array('entity' => $project_owner, 'size' => 'tiny')); ?>
			<p class="pluginsrepo_owner_details"><b>by <a href="<?php echo $vars['url']; ?>pg/plugins/<?php echo $project_owner->username; ?>"><?php echo $project_owner->name; ?></a></b><br />
				<small><b>First uploaded</b> <?php echo $friendlytime; ?></small>
			</p>
			<div class="pluginsrepo_tags">
				<div class="object_tag_string">
					<?php echo elgg_view('output/tags', array('value' => $tags)); ?>
				</div>
			</div>
		</div>
	</div>
	<div class="pluginsrepo_maincontent pluginsrepo_description">
		<div id="recommend">
			<div id="num_recommend">
				<p><?php echo elgg_echo($diggs); ?></p>
			</div>
<?php
				if (!already_dugg($project) && isloggedin()) {
					$url = "{$vars['url']}action/plugins/digg?guid={$project_guid}";
					$url = elgg_add_action_tokens_to_url($url);
					echo "<div id=\"recommend_action\">";
					echo "<a href=\"{$url}\">Recommend</a>";
					echo "</div>";
				} else {
					echo "<div id=\"recommend_action\">";
					echo "<p>Recommendations</p>";
					echo "</div>";
				}
?>
		</div>
		<div class="pluginsrepo_summary">
			<p><b>Summary:</b> <?php echo autop($summary); ?>
		</div>
		<p><b>Full description:</b><?php echo autop($desc); ?></p>
	</div>
</div>
<?php

		if ($release) {
			echo elgg_view_entity($release);
		}

		break;
}
