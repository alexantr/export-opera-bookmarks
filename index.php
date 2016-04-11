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
    if (!is_array($links)) {
        return '';
    }
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
            $name = !empty($one['name']) ? $one['name'] : $one['url'];
            $html .= str_repeat('    ', $depth) . '<DT><A HREF="' . $one['url'] . '"' . $add_date . $last_modified . '>' . $name . '</A>' . "\n";
        }
    }
    if (!$no_dl) $html .= str_repeat('    ', $depth - 1) . '</DL><p>' . "\n";
    return $html;
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

if (!empty($json_data)) {

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
    <style>
        body { font: 14px/1.2 Arial, sans-serif; }
        a { color: #2b6fb6; text-decoration: none; }
        a:hover, a:active { color: red; }
        samp { font-family: Consolas, monospace; }
    </style>
</head>
<body>

<h1>Export Opera bookmarks to Html</h1>

<p>Default file location: <samp>%APPDATA%\Opera Software\Opera Stable\Bookmarks</samp></p>

<form action="" method="post" enctype="multipart/form-data">
    <p><input type="file" name="file_json"></p>
    <p><input type="submit" name="submit" value="Export to Html"></p>
</form>

<?php if (isset($_POST['submit'])): ?>
    <p>Please select file.</p>
<?php endif; ?>

<p><small><a href="https://github.com/alexantr/export-opera-bookmarks">View source on GitHub</a></small></p>

</body>
</html>