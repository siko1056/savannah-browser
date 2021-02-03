<?php

class CONFIG
{
  /**
   * Configurable constant parameters crawler.
   */
  const BASE_URL      = 'https://savannah.gnu.org';
  const CHUNK_SIZE    = 150;

  /**
   * Configurable constant parameters database.
   */
  const DB_FILE       = 'savannah.cache.sqlite';

  /**
   * Common data structures for the database and crawler (interface).
   *
   * Alter with care!  "ID" is a reserved database column name.
   */
  const ITEM_DATA = array(
  // label on website            database column  , database datatype
    'ID:'               => array('ItemID'         , 'INTEGER'  ),
    'TrackerID:'        => array('TrackerID'      , 'INTEGER'  ),
    'Title:'            => array('Title'          , 'TEXT'     ),
    'Submitted by:'     => array('SubmittedBy'    , 'TEXT'     ),
    'Submitted on:'     => array('SubmittedOn'    , 'TIMESTAMP'),
    'Category:'         => array('Category'       , 'TEXT'     ),
    'Severity:'         => array('Severity'       , 'TEXT'     ),
    'Priority:'         => array('Priority'       , 'TEXT'     ),
    'Item Group:'       => array('ItemGroup'      , 'TEXT'     ),
    'Status:'           => array('Status'         , 'TEXT'     ),
    'Assigned to:'      => array('AssignedTo'     , 'TEXT'     ),
    'Originator Name:'  => array('OriginatorName' , 'TEXT'     ),
    'Open/Closed:'      => array('OpenClosed'     , 'BOOLEAN'  ),
    'Release:'          => array('Release'        , 'TEXT'     ),
    'Operating System:' => array('OperatingSystem', 'TEXT'     )
    );

  const DISCUSSION_DATA = array(
  //      database column, database datatype
    array('Date'         , 'TIMESTAMP'),
    array('Author'       , 'TEXT'     ),
    array('Text'         , 'LONGTEXT' )
    );

  // Currently supported Savannah trackers as IDs to not waste space
  // in the database.
  const TRACKER_ID = array(
    'bugs'  => 1,
    'patch' => 2
    );
}

?>
