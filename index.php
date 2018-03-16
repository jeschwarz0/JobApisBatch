<?php 
    $JSON_FMT = strlen(getenv("JAB_OUTPUT_JSON") > 0);
    if (!$JSON_FMT):
?>
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

table.listing .catmatch{
    color: green;
}

table.listing .catmismatch{
    color: red;
}

table.listing .titlematch{
    weight: bold;
    font-family: 'Impact', 'Tahoma', 'Serif';
}

table.listing .globalcat{
    font-style: italic;
}

table.listing tr.filter_deny{
    display:none;
    /*text-decoration: line-through;*/
}
</style>
</head>
<body>

<h1>Job Apis Batch - <?php echo date("m/d/y"); ?></h1>

<?php
endif;
require __DIR__ . '/vendor/autoload.php';

#region Init
// Preload variables
$location_list = explode(':', getenv("JAB_LOCATIONS"));
$keyword_list = explode(':', getenv("JAB_KEYWORDS"));
$max_age = intval(getenv("JAB_MAX_AGE"));
$disable_analysis = strlen(getenv("JAB_DISABLE_ANALYZER")) > 0;
#endregion
// Process the records to desired format
process(!$JSON_FMT);

#region Providers

function fetchTest($keyword, $location)
{
    define('TEST_DATA_PATH', getenv("HOME") . "/.config/JobApisBatch/testjobs.json");
    $jobs = new \JobApis\Jobs\Client\Collection();
    if (PHP_OS === 'Linux' && file_exists(TEST_DATA_PATH))
    {
        try {
            $json_list = json_decode(file_get_contents(TEST_DATA_PATH), false);
            foreach ($json_list as $json_obj){
                $jobs->add($json_obj);
            }
        }catch (Exception $ex){
           // Do nothing
        }      
    }
    return $jobs;
}

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

function fetchGoRemote($keyword, $location)
{
    $query = new JobApis\Jobs\Client\Queries\GoRemoteQuery();
    $client = new JobApis\Jobs\Client\Providers\GoRemoteProvider($query);
    try {
        $jobs = $client->getJobs();
    }
    catch (Exception $ex){
        $jobs = new \JobApis\Jobs\Client\Collection();
    }
    return $jobs;
}

function fetchPHPJobs($keyword, $location)
{
    $query = new JobApis\Jobs\Client\Queries\PhpjobsQuery();
    $query  -> set('country_code', 'us')
            -> set('search_string', $keyword);
    $client = new JobApis\Jobs\Client\Providers\PhpjobsProvider($query);
    try {
        $jobs = $client->getJobs();
    }
    catch (Exception $ex){
        $jobs = new \JobApis\Jobs\Client\Collection();
    }
    return $jobs;
}

function fetchJobsHQ($keyword, $location)
{
    $query = new JobApis\Jobs\Client\Queries\JobsHQQuery();
    $query  -> set('countrycode', 'US')
            -> set('keywords', $keyword)
            -> set('radialtown', $location)
            -> set('role', 43)
            -> set('nearfacetsshown', true);
    $client = new JobApis\Jobs\Client\Providers\JobsHQProvider($query);
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
    $output = '';
    $PROVIDERS = explode(":", getenv("JAB_PROVIDERS"));
    global $location_list, $keyword_list;
    if (!$to_html) $list = new \JobApis\Jobs\Client\Collection();
    // Loop every location then keyword and create a table for each
    foreach ($location_list as $cur_location) {
        if ($to_html) {
            $output .= "<h2>$cur_location</h2>" . PHP_EOL;
        }
        foreach ($keyword_list as $cur_keyword) {
            if ($to_html) {
                $output .= "<h3>$cur_keyword</h3>" . PHP_EOL;
                $list = new \JobApis\Jobs\Client\Collection();
            }
            foreach ($PROVIDERS as $client) {
                if (is_callable("fetch" . $client)) {
                    $next = call_user_func("fetch" . $client, $cur_keyword, $cur_location);
                    if (get_class($next) === 'JobApis\Jobs\Client\Collection') {
                        $list->addCollection($next);
                    }
                }
            } // Print the resulting list
            if ($to_html) {
                printToTable($output, $list);
            } 
        }// Process JSON Result
        if (!$to_html){ $output .= json_encode($list->all()) . PHP_EOL; }
    }
    echo $output;
}

// Prints a collection of jobs to a table
function printToTable(&$output, $collection)
{
    global $disable_analysis;
    if ($collection !== null && count($collection) > 0) {
        if (!$disable_analysis) {
            $analyzer = mkAnalyzer();
            buildPercentTables($analyzer);
            $fset = getFilterSettings();
        }
        $output .= "<table class=\"listing\">" . PHP_EOL;
        $output .= "\t<thead>" . PHP_EOL;
        $output .= "\t\t<tr>" . PHP_EOL;
        $output .= "\t\t\t<th>Name</th><th>Location</th><th>company</th><th>Salary</th><th>Posted</th><th>Deadline</th><th>Source</th>" . PHP_EOL;
        if (!$disable_analysis) $output .= "\t\t\t<th>Score</th>" . PHP_EOL;
        $output .= "\t\t</tr>" . PHP_EOL;
        $output .= "\t</thead>" . PHP_EOL;
        $output .= "\t<tbody>" . PHP_EOL;
        foreach ($collection->all() as $job) {
            if (!$disable_analysis){
                $scores = analyzePositionToArray($job, $analyzer);
                $filter_class = positionMeetsThreshold($scores, $fset) ? 'filter_accept' : 'filter_deny';
            }
            $output .= "\t\t<tr class=\"" . (isset($filter_class) ? $filter_class : "") . "\">" . PHP_EOL;
            $output .= "\t\t\t<td><a href=\"$job->url\" target=\"_blank\">$job->name</a></td>" . PHP_EOL;
            $output .= "\t\t\t<td>$job->location</td>" . PHP_EOL;
            $output .= "\t\t\t<td>" . htmlspecialchars($job->company) . "</td>" . PHP_EOL;
            $output .= "\t\t\t<td>$job->baseSalary</td>" . PHP_EOL;
            $output .= "\t\t\t<td>" . formatDate($job->datePosted) . "</td>" . PHP_EOL;
            $output .= "\t\t\t<td>" . formatDate($job->endDate) . "</td>" . PHP_EOL;
            $output .= "\t\t\t<td>$job->source</td>" . PHP_EOL;
            if (!$disable_analysis){
                $output .= "\t\t\t<td>" . PHP_EOL . writeAnalysisSummary($scores) . "\t\t\t</td>" . PHP_EOL;
            }
            $output .= "\t\t</tr>" . PHP_EOL;
        }
        $output .= "\t</tbody>" . PHP_EOL . "</table>" . PHP_EOL;
    }
}

// Formats a date or empty if null
function formatDate($date)
{
    $returnval = "";
    if ($date !== null) {
        $returnval = (is_string($date) ? new DateTime($date) :$date)->format("m/d/y");
    }
    return $returnval;
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

function positionMeetsThreshold(&$scores, &$filterSettings)
{
    $result = true;
    if ($filterSettings !== FALSE && is_array($scores) && is_array($filterSettings))
    {
        for($fsid = 0; $result && $fsid < count($filterSettings); $fsid++)
        {
            $cat_score = array_values($scores)[$filterSettings[$fsid][0]]; 
            if ((!$cat_score['title_match']) && $cat_score['pct'] < $filterSettings[$fsid][1])
                $result = false;
        }
    }
    return $result;
}

function getFilterSettings()
{
    $result = array();
    $filter_list =  explode(':', getenv('JAB_FILTER_THRESHOLD'));
    for($fi = 0; $fi < count($filter_list); $fi++)
    {
        $det = explode('@', $filter_list[$fi]);
        if (count($det) === 2){
            $appres = array();
            $appres[0] = intval($det[0]);
            $appres[1] = intval($det[1]);
            array_push($result, $appres);
        }
    }
    return count($result) > 0 ? $result : FALSE;
}
#endregion
#region Analyzer

/** Analyzes position description and applies scores based on keywords. */
function analyzePositionToArray(&$position,&$analyzer)
{
    $config_version = intval($analyzer->ConfigVersion);
    if ($config_version > 2) return FALSE;
    $result = array();
    foreach($analyzer->SearchCategory as $categoryArr){
        $result[(string)$categoryArr->Name] = array();
        $result[(string)$categoryArr->Name]['entries'] = array();
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
                $entryScore = intval($found ? $categoryVal->MatchValue : $categoryVal->NonMatchValue);
                $result[(string)$categoryArr->Name]['entries'][(string)$categoryVal->EntryName] = $entryScore;
            }
        }
        $sum = array_sum($result[(string)$categoryArr->Name]['entries']);
        $result[(string)$categoryArr->Name]['sum'] = $sum;
        $result[(string)$categoryArr->Name]['pct'] = calculatePercentage($sum,$categoryArr['min'],$categoryArr['max']);
        if ($config_version >= 2){
            $title_match = false;
            if (isset($categoryArr->CategoryTitle)){
                for($titleIdx = 0;!$title_match && $titleIdx < $categoryArr->CategoryTitle->Term->count(); $titleIdx++)
                {
                    if (stripos($position->title, (string)$categoryArr->CategoryTitle->Term[$titleIdx]) !== FALSE)
                        $title_match = true;
                }
            }
            $result[(string)$categoryArr->Name]['title_match'] = $title_match;
            $result[(string)$categoryArr->Name]['is_global'] = $categoryArr['isglobal'];
        }
    }
    return $result;
}

function buildPercentTables(&$analyzer)
{
    if (intval($analyzer->ConfigVersion) <= 0) return FALSE;
    foreach($analyzer->SearchCategory as $categoryArr){
        $catmin = 0;
        $catmax = 0;
        foreach($categoryArr->CategoryValue as $categoryVal){
            $catmin += min(intval($categoryVal->MatchValue), intval($categoryVal->NonMatchValue));
            $catmax += max(intval($categoryVal->MatchValue), intval($categoryVal->NonMatchValue));
        }
        $categoryArr->addAttribute('min', $catmin);
        $categoryArr->addAttribute('max', $catmax);
    }
}

function calculatePercentage(&$sum, $min, $max)
{
    $divisor = intval($sum > 0 ? $max : $min);
    $pct = 0;
    if ($sum !== 0 && $divisor !== 0)
         $pct = ($sum / $divisor) * 100;
    if ($sum < 0 || $divisor < 0)
        $pct *= -1;
    return round($pct);
}

function writeAnalysisSummary(&$data)
{ 
    $html = '';
    if (is_array($data)){
        $html .= "\t\t\t<ul>" . PHP_EOL;
        foreach ($data as $categoryKey => $categoryValue){
            if (intval($categoryValue['sum']) !== 0){
                $verbose_summary = 'title="' . $categoryValue['sum'] . ': ';
                foreach($categoryValue['entries'] as $entryKey => $entryValue){
                    if ($entryValue !== 0)
                        $verbose_summary .= htmlspecialchars($entryKey) . "($entryValue) ";
                }
                $verbose_summary .= '"';
                $html.= "\t\t\t\t<li $verbose_summary" . ' class="' . ($categoryValue['pct'] >= 0 ? 'catmatch' : 'catmismatch') . ($categoryValue['title_match'] ? ' titlematch' : '') . ($categoryValue['is_global'] ? ' globalcat' : '') . '">' . htmlspecialchars($categoryKey) . " : " . $categoryValue['pct'] . "%</li>" . PHP_EOL;
            }
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

#endregion
if (!$JSON_FMT):
?>
</body>
</html>
<?php endif;?>
