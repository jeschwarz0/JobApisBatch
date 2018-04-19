<?php // Determine if output is HTML or JSON
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

table.listing .pa-catmatch{
    color: green;
}

table.listing .pa-catmismatch{
    color: red;
}

table.listing .pa-titlematch{
    weight: bold;
    font-family: 'Impact', 'Tahoma', 'Serif';
}

table.listing .pa-globalcat{
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

define('CONFIG_PATH', PHP_OS === 'Linux' ? getenv("HOME") . "/.config/JobApisBatch/" : getenv("APPDATA") . "/JobApisBatch/");
// Process the records to desired format
process(!$JSON_FMT);
// These functions will provide a collection of Job objects with keyword and location as parameters
#region Providers

/**
 * Provides test records from a predefined JSON file.
 * @param string $keyword The keyword to search for.
 * @param string $location The location to search for.
 * @return \JobApis\Jobs\Client\Collection A collection of \JobAPis\Jobs\Client\Job objects.
 */
function fetchTest($keyword, $location)
{
    define('TEST_DATA_PATH', CONFIG_PATH . 'testjobs.json');
    $jobs = new \JobApis\Jobs\Client\Collection();
    if (file_exists(TEST_DATA_PATH))
    {
        try {
            $json_list = json_decode(file_get_contents(TEST_DATA_PATH), false);
            if ($json_list !== NULL)
                foreach ($json_list as $json_obj){
                    $jobs->add($json_obj);
                }
        }catch (Exception $ex){
           // Do nothing
        }      
    }
    return $jobs;
}

/**
 * Provides records from multiple sources using Jobs Multi.
 * Loads additional providers from keys.json.
 * @param string $keyword The keyword to search for.
 * @param string $location The location to search for.
 * @return \JobApis\Jobs\Client\Collection A collection of \JobAPis\Jobs\Client\Job objects.
 */
function fetchJobsMulti($keyword, $location)
{
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
        'maxAge' => intval(getenv("JAB_MAX_AGE")),
        'maxResults' => 0,
        'orderBy' => 'datePosted',
        'order' => 'desc',
    ];

    // Get all the jobs
    return $multi_client->getAllJobs($options);
}

/**
 * Fetches records from the Craigslist API.
 * @param string $keyword The keyword to search for.
 * @param string $location The location to search for.
 * @return \JobApis\Jobs\Client\Collection A collection of \JobAPis\Jobs\Client\Job objects.
 */
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

/**
 * Fetches records from the GoRemote API.
 * @param string $keyword The keyword to search for.
 * @param string $location The location to search for.
 * @return \JobApis\Jobs\Client\Collection A collection of \JobAPis\Jobs\Client\Job objects.
 */
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

/**
 * Fetches records from the PHPJobs API.
 * @param string $keyword The keyword to search for.
 * @param string $location The location to search for.
 * @return \JobApis\Jobs\Client\Collection A collection of \JobAPis\Jobs\Client\Job objects.
 */
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

/**
 * Fetches records from the custom JobsHQ API.
 * @param string $keyword The keyword to search for.
 * @param string $location The location to search for.
 * @return \JobApis\Jobs\Client\Collection A collection of \JobAPis\Jobs\Client\Job objects.
 */
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
/**
 * Invokes the primary process of the JAB.
 * Loops through the providers, keywords and locations.
 * @param bool $to_html If true render html, otherwise JSON.
 */
function process($to_html = true)
{  
    $output = '';
    $PROVIDERS = explode(":", getenv("JAB_PROVIDERS"));
    $LOCATION_LIST = explode(':', getenv("JAB_LOCATIONS"));
    $KEYWORD_LIST = explode(':', getenv("JAB_KEYWORDS"));
    if (!$to_html) $list = new \JobApis\Jobs\Client\Collection();
    // Loop every location then keyword and create a table for each
    foreach ($LOCATION_LIST as $cur_location) {
        if ($to_html) {
            $output .= "<h2>$cur_location</h2>" . PHP_EOL;
        }
        foreach ($KEYWORD_LIST as $cur_keyword) {
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

/** 
 *  Generates an HTML table from the collection.
 * @param string The referenced string to append the completed HTML to.
 * @param \JobApis\Jobs\Client\Collection The collection to process.
 */
 function printToTable(&$output, $collection)
{
    $disable_analysis = strlen(getenv("JAB_DISABLE_ANALYZER")) > 0;
    if ($collection !== null && count($collection) > 0) {
        if (!$disable_analysis) {
            $analyzer = new \JobApis\Utilities\PositionAnalyzer(CONFIG_PATH . 'preferences.xml');
            $fset = getFilterSettings();
            $desc_len_req = intval(getenv("JAB_LENGTH_REQ"));
        }
        $output .= "<table class=\"listing\">" . PHP_EOL;
        $output .= "\t<thead>" . PHP_EOL;
        $output .= "\t\t<tr>" . PHP_EOL;
        $output .= "\t\t\t<th>Name</th><th>Location</th><th>Company</th><th>Posted</th><th>Source</th>" . PHP_EOL;
        if (!$disable_analysis) $output .= "\t\t\t<th>Score</th>" . PHP_EOL;
        $output .= "\t\t</tr>" . PHP_EOL;
        $output .= "\t</thead>" . PHP_EOL;
        $output .= "\t<tbody>" . PHP_EOL;
        foreach ($collection->all() as $job) {
            if (!$disable_analysis){
                // Check if length is sufficient to analyze
                $alen = $desc_len_req <= 0 || strlen($job->description) >= $desc_len_req;
                if ($alen){
                    $scores = $analyzer->analyzePositionToArray($job);
                    $filter_class = positionMeetsThreshold($scores, $fset) ? 'filter_accept' : 'filter_deny';
                }
            }
            $output .= "\t\t<tr class=\"" . (isset($filter_class) ? $filter_class : "") . "\">" . PHP_EOL;
            $output .= "\t\t\t<td><a href=\"$job->url\" target=\"_blank\">$job->name</a></td>" . PHP_EOL;
            $output .= "\t\t\t<td>$job->location</td>" . PHP_EOL;
            $output .= "\t\t\t<td>" . htmlspecialchars($job->company) . "</td>" . PHP_EOL;
            $output .= "\t\t\t<td>" . formatDate($job->datePosted) . "</td>" . PHP_EOL;
            $output .= "\t\t\t<td>$job->source</td>" . PHP_EOL;
            if (!$disable_analysis){
                $output .= "\t\t\t<td>" . PHP_EOL . ($alen ? \JobApis\Utilities\PositionAnalyzer::BuildSummaryList1($scores, 4) : '') . "\t\t\t</td>" . PHP_EOL;
            }
            $output .= "\t\t</tr>" . PHP_EOL;
        }
        $output .= "\t</tbody>" . PHP_EOL . "</table>" . PHP_EOL;
    }
}
/** 
 * Formats a date to be printed.
 * @param DateTime|string The date input string.
 * @return string The input date printed as a string or an empty string.
 */
function formatDate($date)
{
    $returnval = "";
    if ($date !== null) {
        $returnval = (is_string($date) ? new DateTime($date) :$date)->format("m/d/y");
    }
    return $returnval;
}

/** 
 * Appends providers for JobsMulti which require credentials to the list of providers.
 * Requires keys.json to be in the path and contain the required keys.
 * @param array A reference to the original list of providers.
 */
function appendRestrictedProviders(&$providers)
{
    $keys_path = CONFIG_PATH . 'keys.json';
    if (file_exists($keys_path)) {
        $keys_json = file_get_contents($keys_path);
        unset($keys_path);
        if ($keys_json !== false) {
            $keys = json_decode($keys_json, true);
            unset($keys_json);
            // Build the array\
            $nprov = array();
            foreach ($keys as $key => $keyvalue){
                $keyval = explode('.', $key);
                if (!array_key_exists($keyval[0], $nprov))
                    $nprov[$keyval[0]] = array();
                if (count($keyval) === 2)
                    $nprov[$keyval[0]][$keyval[1]] = $keyvalue;
            };
            // Merge the array
            $providers = array_merge($providers, $nprov);
        }
    }
}

/**
 * Checks an analyser output against filter settings to determine if it meets the criteria.
 * @param array $scores A reference to an array generated by the analyzer.
 * @param array $filterSettings A reference to the filter settings to test against.
 * @return bool Returns true if there are no filter settings, otherwise the result of the check.
 */
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

/**
 * Creates a filter settings array based off the JAB_FILTER_THRESHOLD environment variable.
 * Allows multiple instances separated by ':' which contain 2 integers separated by '@'.
 * The first integer is the ordinal position of the Category as it is listed in the configuration.
 * The second integer is the minimum score that would pass the filtering.
 * @return array|FALSE Returns the array on success, otherwise FALSE.
 */
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

if (!$JSON_FMT):
?>
</body>
</html>
<?php endif;?>
