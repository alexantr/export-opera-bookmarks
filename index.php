<?php

/**
 * @param array $links
 * @param int $depth
 * @param bool $no_dl
 *
 * @return string
 */
function get_links($links, $depth = 1, $no_dl = false)
{
	$html = '';
	if (!$no_dl) $html .= str_repeat('    ', $depth - 1) . '<DL><p>' . "\n";
	foreach ($links as $one) {

		if (!isset($one['type'])) {
			$html .= get_links($one, $depth, true);
			continue;
		}

		$add_date = '';
		if (isset($one['date_added']) && $one['date_added'] != '0') {
			$win_date_added = substr($one['date_added'], 0, 11); // get seconds
			$add_date = ' ADD_DATE="' . ($win_date_added - 11644473600) . '"'; // 1.1.1600 -> 1.1.1970 difference in seconds
		}

		$last_modified = '';
		if (isset($one['date_modified']) && $one['date_modified'] != '0') {
			$win_date_modified = substr($one['date_modified'], 0, 11);
			$last_modified = ' LAST_MODIFIED="' . ($win_date_modified - 11644473600) . '"';
		}

		if ($one['type'] == 'folder') {
			$html .= str_repeat('    ', $depth) . '<DT><H3' . $add_date . $last_modified . '>' . $one['name'] . '</H3>' . "\n";
			$children = isset($one['children']) ? $one['children'] : [];
			$html .= get_links($children, $depth + 1);
		} elseif ($one['type'] == 'url') {
			$html .= str_repeat('    ', $depth) . '<DT><A HREF="' . $one['url'] . '"' . $add_date . $last_modified . '>' . $one['name'] . '</A>' . "\n";
		}
	}
	if (!$no_dl) $html .= str_repeat('    ', $depth - 1) . '</DL><p>' . "\n";
	return $html;
}

/**
 * @param string $db_name
 *
 * @return array
 */
function prepare_db($db_name)
{
	$raw_array = [];

	try {
		$db = new PDO('sqlite:' . $db_name);
		$sth = $db->query('SELECT * FROM favorites ORDER BY idx, type');
		if ($sth) {
			$sth->setFetchMode(PDO::FETCH_ASSOC);
			while($row = $sth->fetch()) {
				$raw_array[] = $row;
			}
		}
	} catch (PDOException $e) {
		//echo $e->getMessage();
		//exit;
	}

	$children = build_tree($raw_array, '');

	$array = [
		[
			'type' => 'folder',
			'name' => 'Speed Dial',
			'children' => $children,
		]
	];

	return $array;
}

/**
 * @param array $items
 * @param mixed $parent_guid
 *
 * @return array
 */
function build_tree($items, $parent_guid)
{
	$result = [];
	foreach ($items as $item) {
		if ($item['parent_guid'] == $parent_guid) {
			if ($item['type'] == '1') {
				$new_item = [
					'type' => 'folder',
					'name' => $item['name'],
				];
				$new_item['children'] = build_tree($items, $item['guid']);
			} else {
				$new_item = [
					'type' => 'url',
					'name' => $item['name'],
					'url' => $item['url'],
				];
			}
			$result[] = $new_item;
		}
	}
	return $result;
}

$tmp_path = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'temp';
if (!is_dir($tmp_path)) {
	@mkdir($tmp_path, 0777, true);
}
if (!is_dir($tmp_path)) {
	exit('Can not create temp folder');
}

// get data from "Bookmarks" file
$json_data = [];
if (empty($_FILES['file_json']['error']) && !empty($_FILES['file_json']['tmp_name']) && $_FILES['file_json']['tmp_name'] != 'none') {
	$json_file = $tmp_path . DIRECTORY_SEPARATOR . md5(microtime()) . '.json';
	move_uploaded_file($_FILES['file_json']['tmp_name'], $json_file);
	if (is_file($json_file)) {
		$json_data = file_get_contents($json_file);
		$json_data = json_decode($json_data, true);
		unlink($json_file);
	}
}

// get data from "favorites.db" file
$db_data = [];
if (empty($_FILES['file_db']['error']) && !empty($_FILES['file_db']['tmp_name']) && $_FILES['file_db']['tmp_name'] != 'none') {
	$db_file = $tmp_path . DIRECTORY_SEPARATOR . md5(microtime()) . '.db';
	move_uploaded_file($_FILES['file_db']['tmp_name'], $db_file);
	if (is_file($db_file)) {
		$db_data = prepare_db($db_file);
		unlink($db_file);
	}
}

if (!empty($json_data) || !empty($db_data)) {

	// https://msdn.microsoft.com/en-us/library/aa753582(v=vs.85).aspx
	$html = <<<HTML
<!DOCTYPE NETSCAPE-Bookmark-file-1>
<!-- This is an automatically generated file.
	 It will be read and overwritten.
	 DO NOT EDIT! -->
<META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
<TITLE>Bookmarks</TITLE>
<H1>Bookmarks</H1>


HTML;
	$html .= '<DL><p>' . "\n";
	if (!empty($json_data) && isset($json_data['roots'])) {
		$html .= get_links($json_data['roots'], 1, true);
	}
	if (!empty($db_data)) {
		$html .= get_links($db_data, 1, true);
	}
	$html .= '</DL>' . "\n";

	// response
	header('Content-Disposition: attachment; filename="bookmarks.html"');
	header('Content-Length: ' . mb_strlen($html, '8bit'));
	header('Content-Type: application/x-force-download; name="bookmarks.html"');
	echo $html;
	exit;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>Export Opera bookmarks to Html</title>
</head>
<body>

<h1>Export Opera bookmarks to Html</h1>

<p>
	Files location example:<br>
	Bookmarks: <samp>%APPDATA%\Opera Software\Opera Stable\Bookmarks</samp><br>
	Speed Dial: <samp>%APPDATA%\Opera Software\Opera Stable\favorites.db</samp>
</p>

<form action="" method="post" enctype="multipart/form-data">
	<label>Bookmarks: <input type="file" name="file_json"></label><br>
	<label>favorites.db: <input type="file" name="file_db"></label><br>
	<br>
	<input type="submit" name="submit" value="Export to Html">
</form>

<?php if (isset($_POST['submit'])): ?>
	<p>Please select file(s).</p>
<?php endif; ?>

<p><small><a href="https://github.com/alexantr/export-opera-bookmarks">View on GitHub</a></small></p>

</body>
</html>