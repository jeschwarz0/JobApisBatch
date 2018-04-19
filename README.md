# Job Apis Batch

Fetches and processes Job records from the JobApis providers and outputs either HTML or JSON.  Searches multiple providers, locations, and keywords with optional filtering and analysis.

## Getting Started

### Prerequisites

```
 PHP >= 7.2.4
 Composer >= 1.6.3
```
Note: *jschwarz/jobs-JobsHQ* and *jschwarz/position-analyzer* are development packages and must be manually consumed

### Installing

1. Clone the repo

```
git clone JobApisBatch.git
```

2. Install composer packages

```
Composer install
```
### Usage
```
php index.php > outputfile.html
```
### Developer Keys
To access JobsMulti providers requiring credentials, create keys.json with any of the following properties:

* Careerbuilder.DeveloperKey
* Careerjet.affid
* Indeed.publisher
* J2c.id
* J2c.pass
* Juju.partnerid
* Usajobs.AuthorizationKey
* Ziprecruiter.api_key
### Environment Variables

* **JAB_OUTPUT_JSON** - If set, output result as JSON, otherwise HTML
* **JAB_PROVIDERS** - Gets the names of provider functions to use, without 'Provider' appended to name, separated by ':'
* **JAB_LOCATIONS** - Gets the locations to search, separated by ':'
* **JAB_KEYWORDS** - Gets the keywords to search, separated by ':'
* **JAB_DISABLE_ANALYZER** - If set, disable analyzer functionality (and filtering)
* **JAB_FILTER_THRESHOLD** - Gets the filter threshold entries (CategoryIndex@MinPct) separated by ':'
* **JAB_LENGTH_REQ** - If set, disable analyzer functionality for jobs with a description character count less than value
* **JAB_MAX_AGE** - Gets the number of days to search for (where implemented)

## Authors

* **Jesse Schwarz** - [Linkedin](www.linkedin.com/in/jesse-schwarz-56311652)


## License

This project is licensed under the Default license

## Acknowledgments

* [JobApis](http://www.jobapis.com) Community