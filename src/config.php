<?php

class CONFIG
{
  /**
   * Configurable constant parameters crawler.
   */
  const URL           = 'https://savannah.gnu.org';
  const TRACKER       = 'bugs';
  const GROUP         = 'octave';
  const CHUNK_SIZE    = 150;

  /**
   * Configurable constant parameters database.
   */
  const DB_FILE       = 'savannah.cache.sqlite';

  /**
   * Common data structures for the database and crawler (interface).
   *
   * Alter with care!  First row "ID" is assumed to exist.
   */
  const BUGS_DATA = array(
  // label on website        , database name    , database datatype
    array('ID:'              , 'ID'             , 'INTEGER PRIMARY KEY'),
    array('Title:'           , 'Title'          , 'TEXT'               ),
    array('Submitted by:'    , 'SubmittedBy'    , 'TEXT'               ),
    array('Submitted on:'    , 'SubmittedOn'    , 'TIMESTAMP'          ),
    array('Category:'        , 'Category'       , 'TEXT'               ),
    array('Severity:'        , 'Severity'       , 'TEXT'               ),
    array('Priority:'        , 'Priority'       , 'TEXT'               ),
    array('Item Group:'      , 'ItemGroup'      , 'TEXT'               ),
    array('Status:'          , 'Status'         , 'TEXT'               ),
    array('Assigned to:'     , 'AssignedTo'     , 'TEXT'               ),
    array('Originator Name:' , 'OriginatorName' , 'TEXT'               ),
    array('Open/Closed:'     , 'OpenClosed'     , 'BOOLEAN'            ),
    array('Release:'         , 'Release'        , 'TEXT'               ),
    array('Operating System:', 'OperatingSystem', 'TEXT'               )
    );
}

?>
