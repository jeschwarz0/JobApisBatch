<!DOCTYPE html>
<html>
<head>
<title>JobApis Batch</title>
<style>
/* Sample table from w3schools */
table.listing {
    font-family: "Trebuchet MS", Arial, Helvetica, sans-serif;
    border-collapse: collapse;
    width: 100%;
}

table.listing td, table.listing th {
    border: 1px solid #ddd;
    padding: 8px;
}

table.listing tr:nth-child(even){background-color: #f2f2f2;}

table.listing tr:hover {background-color: #ddd;}

table.listing th {
    padding-top: 12px;
    padding-bottom: 12px;
    text-align: left;
    background-color: #4CAF50;
    color: white;
}
</style>
</head>
<body>

<h1>Job Apis Batch - <?php echo date("m/d/y"); ?></h1>

<?php
require __DIR__ . '/vendor/autoload.php';

#region Init
// Preload variables
$location_list = explode(':', getenv("JAB_LOCATIONS"));
$keyword_list = explode(':', getenv("JAB_KEYWORDS"));
$max_age = intval(getenv("JAB_MAX_AGE"));
$disable_analysis = strlen(getenv("JAB_DISABLE_ANALYZER")) > 0;
#endregion
// Process the records to desired format
process(true);

#region Providers

function fetchJobsMulti($keyword, $location)
{
    global $max_age;

    $providers = [
        'Careercast' => [],
        'Github' => [],
        'Govt' => [],
        'Ieee' => [],
        'Jobinventory' => [],
        'Monster' => [],
        'Stackoverflow' => [],
    ];

    appendRestrictedProviders($providers);

    $multi_client = new \JobApis\Jobs\Client\JobsMulti($providers);

    $multi_client
        ->setKeyword($keyword)
        ->setLocation($location);

    $options = [
        'maxAge' => $max_age,
        'maxResults' => 0,
        'orderBy' => 'datePosted',
        'order' => 'desc',
    ];

    // Get all the jobs
    return $multi_client->getAllJobs($options);
}

function fetchCraigslist($keyword, $location)
{
    $query = new JobApis\Jobs\Client\Queries\CraigslistQuery();
    $query 
        ->set('query', strtolower($keyword))
        ->set('location', strtolower(explode(",", $location)[0]))
        ->set('searchNearby', '1');

    $client = new JobApis\Jobs\Client\Providers\CraigslistProvider($query);
    try { 
        $jobs = $client->getJobs();
    }
    catch (Exception $ex){
        $jobs = new \JobApis\Jobs\Client\Collection();
    }
    return $jobs;
   
}

#endregion
#region Helper Functions
function process($to_html = true)
{
    $PROVIDERS = array("fetchJobsMulti", "fetchCraigslist");
    global $location_list, $keyword_list;
    // Loop every location then keyword and create a table for each
    foreach ($location_list as $cur_location) {
        if ($to_html) {
            echo "<h2>$cur_location</h2>" . PHP_EOL;
        }

        foreach ($keyword_list as $cur_keyword) {
            if ($to_html) {
                echo "<h3>$cur_keyword</h3>" . PHP_EOL;
            }

            $list = new \JobApis\Jobs\Client\Collection();
            foreach ($PROVIDERS as $client) {
                if (is_callable($client)) {
                    $next = call_user_func($client, $cur_keyword, $cur_location);
                    if (get_class($next) === 'JobApis\Jobs\Client\Collection') {
                        $list->addCollection($next);
                    }

                }
            } // Print the resulting list
            if ($to_html) {
                printToTable($list);
            } else // Assume JSON
            {
                echo json_encode($list->all());
            }

        }
    }
}

// Prints a collection of jobs to a table
function printToTable($collection)
{
    global $disable_analysis;
    if ($collection !== null && count($collection) > 0) {
        if (!$disable_analysis) $analyzer = mkAnalyzer();
        echo "<table class=\"listing\">" . PHP_EOL;
        echo "\t<thead>" . PHP_EOL;
        echo "\t\t<tr>" . PHP_EOL;
        echo "\t\t\t<th>Name</th><th>Location</th><th>company</th><th>Salary</th><th>Posted</th><th>Deadline</th><th>Source</th>" . PHP_EOL;
        if (!$disable_analysis) echo "\t\t\t<th>Score</th>" . PHP_EOL;
        echo "\t\t</tr>" . PHP_EOL;
        echo "\t</thead>" . PHP_EOL;
        echo "\t<tbody>" . PHP_EOL;
        foreach ($collection->all() as $job) {
            echo "\t\t<tr>" . PHP_EOL;
            echo "\t\t\t<td><a href=\"$job->url\" target=\"_blank\">$job->name</a></td>" . PHP_EOL;
            echo "\t\t\t<td>$job->location</td>" . PHP_EOL;
            echo "\t\t\t<td>" . htmlspecialchars($job->company) . "</td>" . PHP_EOL;
            echo "\t\t\t<td>$job->baseSalary</td>" . PHP_EOL;
            echo "\t\t\t<td>" . formatDate($job->datePosted) . "</td>" . PHP_EOL;
            echo "\t\t\t<td>" . formatDate($job->endDate) . "</td>" . PHP_EOL;
            echo "\t\t\t<td>$job->source</td>" . PHP_EOL;
            if (!$disable_analysis){
                $scores = analyzePositionToArray($job, $analyzer);
                echo "\t\t\t<td>" . PHP_EOL . writeAnalysisSummary($scores) . "\t\t\t</td>" . PHP_EOL;
            }
            echo "\t\t</tr>" . PHP_EOL;
        }
        echo "\t</tbody>" . PHP_EOL . "</table>" . PHP_EOL;
    }
}

// Formats a date or empty if null
function formatDate($date)
{
    $returnval = "";
    if ($date !== null) {
        $returnval = $date->format("m/d/y");
    }
    return $returnval;
}
#endregion
#region Analyzer

/** Analyzes position description and applies scores based on keywords. */
function analyzePositionToArray(&$position,&$analyzer)
{
    if (intval($analyzer->ConfigVersion) !== 1) return FALSE;
    $result = array();
    foreach($analyzer->SearchCategory as $categoryArr){
        $result[(string)$categoryArr->Name] = array();
        foreach($categoryArr->CategoryValue as $categoryVal){
            $matchIdx = -1;
            for ($entryIdx = 0; $matchIdx === -1 && $entryIdx < $analyzer->SearchEntry->count(); $entryIdx++){
                if (strcmp($analyzer->SearchEntry[$entryIdx]->Name, $categoryVal->EntryName) === 0){
                    $matchIdx = $entryIdx;
                }
            }
            if ($matchIdx !== -1){
                $found = false;
                for ($tidx = 0; !$found && $tidx < $analyzer->SearchEntry[$matchIdx]->SearchTerms->Term->count(); $tidx++){
                    if (stripos($position->description, (string)$analyzer->SearchEntry[$matchIdx]->SearchTerms->Term[$tidx][0]) !== FALSE)
                        $found = true;
                }
                $mod = intval($found ? $categoryVal->MatchValue : $categoryVal->NonMatchValue);
                $result[(string)$categoryArr->Name][(string)$categoryVal->EntryName] = $mod;
            }
        }
    }
    return $result;
}

function writeAnalysisSummary(&$data)
{ 
    $html = '';
    if (is_array($data)){
        $html .= "\t\t\t<ul>" . PHP_EOL;
        foreach ($data as $categoryKey => $categoryValue){
            $verbose_summary = 'title="';
            foreach($categoryValue as $entryKey => $entryValue){
                if ($entryValue !== 0)
                    $verbose_summary .= htmlspecialchars($entryKey) . "($entryValue) ";
            }
            $verbose_summary .= '"';
            $html.= "\t\t\t\t<li $verbose_summary>" . htmlspecialchars($categoryKey) . " : " . array_sum($categoryValue) . "</li>" . PHP_EOL;

        }
        $html .= "\t\t\t</ul>" . PHP_EOL;
    }
    return $html;
}

function mkAnalyzer()
{
    $FN = PHP_OS === 'Linux' ? getenv("HOME") . "/.config/JobApisBatch/preferences.xml" : getenv("APPDATA") . "/JobApisBatch/preferences.xml";
    $fil = file_get_contents($FN) or die("Failed to load preferences.xml");
    $parse = simplexml_load_string($fil) or die("Failed to parse preferences.xml");
    return $parse;
}

function appendRestrictedProviders(&$providers)
{
    $keys_path = 'keys.json';
    if (file_exists($keys_path)) {
        $keys_json = file_get_contents($keys_path);
        unset($keys_path);
        if ($keys_json !== false) {
            $keys = json_decode($keys_json, true);
            unset($keys_json);
            // Build the array
            $nprov = [
                'Careerbuilder' => [
                    'DeveloperKey' => $keys['Careerbuilder.DeveloperKey'],
                ],
                'Careerjet' => [
                    'affid' => $keys['Careerjet.affid'],
                ],
                'Indeed' => [
                    'publisher' => $keys['Indeed.publisher'],
                ],
                'J2c' => [
                    'id' => $keys['J2c.id'],
                    'pass' => $keys['J2c.pass'],
                ],
                'Juju' => [
                    'partnerid' => $keys['Juju.partnerid'],
                ],
                'Usajobs' => [
                    'AuthorizationKey' => $keys['Usajobs.AuthorizationKey'],
                ],
                'Ziprecruiter' => [
                    'api_key' => $keys['Ziprecruiter.api_key'],
                ],
            ]; // Merge the array
            $providers = array_merge($providers, $nprov);
        }
    }
}

#endregion
?>
</body>
</html>