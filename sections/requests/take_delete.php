<?
//******************************************************************************//
//--------------- Delete request -----------------------------------------------//

authorize();

$RequestID = $_POST['id'];
if (!is_number($RequestID)) {
	error(0);
}

$DB->query("
	SELECT
		UserID,
		Title,
		CategoryID,
		GroupID
	FROM requests
	WHERE ID = $RequestID");
list($UserID, $Title, $CategoryID, $GroupID) = $DB->next_record();

if ($LoggedUser['ID'] != $UserID && !check_perms('site_moderate_requests')) {
	error(403);
}

$CategoryName = $Categories[$CategoryID - 1];

//Do we need to get artists?
$FullName = $Title;



// Delete request, votes and tags
$DB->query("DELETE FROM requests WHERE ID = '$RequestID'");
$DB->query("DELETE FROM requests_votes WHERE RequestID = '$RequestID'");
$DB->query("DELETE FROM requests_tags WHERE RequestID = '$RequestID'");
Comments::delete_page('requests', $RequestID);

$DB->query("
	SELECT ArtistID
	FROM requests_artists
	WHERE RequestID = $RequestID");
$RequestArtists = $DB->to_array();
foreach ($RequestArtists as $RequestArtist) {
	$Cache->delete_value("artists_requests_$RequestArtist");
}
$DB->query("
	DELETE FROM requests_artists
	WHERE RequestID = '$RequestID'");
$Cache->delete_value("request_artists_$RequestID");

G::$DB->query("
	REPLACE INTO sphinx_requests_delta
		(ID)
	VALUES
		($RequestID)");

if ($UserID != $LoggedUser['ID']) {
	Misc::send_pm_with_tpl($UserID, 'request_deleted', [
		'FullName' => $FullName,
		'LoggedUserID' => $LoggedUser['ID'],
		'LoggedUserUsername' => $LoggedUser['Username'],
		'Reason' => $_POST['reason'],
	]);
}

Misc::write_log("Request $RequestID ($FullName) was deleted by user " . $LoggedUser['ID'] . ' (' . $LoggedUser['Username'] . ') for the reason: ' . $_POST['reason']);

$Cache->delete_value("request_$RequestID");
$Cache->delete_value("request_votes_$RequestID");
if ($GroupID) {
	$Cache->delete_value("requests_group_$GroupID");
}

header('Location: requests.php');
