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

<h1>Job Apis Batch - <?php echo date("m/d/y");?></h1>

<?php
require __DIR__ . '/vendor/autoload.php';

#region Init
// Preload variables
$location_list = explode(':', getenv("JAB_LOCATIONS"));
$keyword_list = explode(':', getenv("JAB_KEYWORDS"));
$max_age = intval(getenv("JAB_MAX_AGE"));
// Load Providers

$providers = [
    'Careercast' => [],
    'Dice' => [],
    'Github' => [],
    'Govt' => [],
    'Ieee' => [],
    'Jobinventory' => [],
    'Monster' => [],
    'Stackoverflow' => [],
];

$multi_client = new \JobApis\Jobs\Client\JobsMulti($providers);
#endregion
#region Main
// Loop every location then keyword and create a table for each
foreach ($location_list as $cur_location) {
    echo "<h2>$cur_location</h2>" . PHP_EOL;
    foreach ($keyword_list as $cur_keyword) {
        $list = loadFromProvider($multi_client, $cur_keyword, $cur_location);
        if (count($list) > 0) {
            // Output a summary of jobs as arrays
            # echo $keyword . " in " . $location . count($jobs)." Jobs Found".PHP_EOL;
            echo "<h3>$cur_keyword</h3>" . PHP_EOL;
            echo "<table class=\"listing\">" . PHP_EOL;
            echo "\t<thead>" . PHP_EOL;
            echo "\t\t<tr>" . PHP_EOL;
            echo "\t\t\t<th>Name</th><th>Location</th><th>company</th><th>Salary</th><th>Posted</th><th>Deadline</th><th>Source</th>" . PHP_EOL;
            echo "\t\t</tr>" . PHP_EOL;
            echo "\t</thead>" . PHP_EOL;
            echo "\t<tbody>" . PHP_EOL;
            foreach ($list->all() as $job) {
                echo "\t\t<tr>" . PHP_EOL;
                echo "\t\t\t<td><a href=\"$job->url\" target=\"_blank\">$job->name</a></td>" . PHP_EOL;
                echo "\t\t\t<td>$job->location</td>" . PHP_EOL;
                echo "\t\t\t<td>" . htmlspecialchars($job->company) . "</td>" . PHP_EOL;
                echo "\t\t\t<td>$job->baseSalary</td>" . PHP_EOL;
                echo "\t\t\t<td>" . formatDate($job->datePosted) . "</td>" . PHP_EOL;
                echo "\t\t\t<td>" . formatDate($job->endDate) . "</td>" . PHP_EOL;
                echo "\t\t\t<td>$job->source</td>" . PHP_EOL;
                echo "\t\t</tr>" . PHP_EOL;
            }
            echo "\t</tbody>" . PHP_EOL . "</table>" . PHP_EOL;
        }
    }
}

#endregion
#region Helper Functions
function loadFromProvider($client, $keyword, $location)
{
    global $max_age;
    $client
        ->setKeyword($keyword)
        ->setLocation($location);

    $options = [
        'maxAge' => $max_age,
        'maxResults' => 0,
        'orderBy' => 'datePosted',
        'order' => 'desc',
    ];

    // Get all the jobs
    return $client->getAllJobs($options);
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
?>
</body>
</html>