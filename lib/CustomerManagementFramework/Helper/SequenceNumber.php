<?php
/**
 * Created by PhpStorm.
 * User: mmoser
 * Date: 13.12.2016
 * Time: 14:57
 */

namespace CustomerManagementFramework\Helper;

use Pimcore\Db;

class SequenceNumber
{
    const TABLE_NAME = 'plugin_cmf_sequence_numbers';

    public static function getCurrent($sequenceName, $startingNumber = 10000) {

        $db = Db::get();
        $number = $db->fetchOne("select number from " . self::TABLE_NAME . " where name = ?", $sequenceName);
        return intval($number) ? : $startingNumber;
    }

    public static function setCurrent( $sequenceName, $sequenceValue = 10000  ) {
        $handle = self::SemaphoreWait();
        $current = self::getCurrent( $sequenceName, $sequenceValue );

        try {
            if( $current > $sequenceValue ) {
                throw new \RuntimeException( sprintf(
                    'Current sequence value of %d is greater then desired %d, preventing update!'
                ));
            }
            Db::get()->query("insert into " . self::TABLE_NAME . " (name, number) values (?,?) on duplicate key update number = ?", [$sequenceName, $sequenceValue, $sequenceValue]);

            $logger = \Pimcore::getDiContainer()->get("CustomerManagementFramework\\Logger");

            $logger->info( sprintf(
                "Updated Sequence Number '%s' from %d to %d (pid :%s)",
                $sequenceName, $current, $sequenceValue, getmypid()
            ) );

            return self::getCurrent( $sequenceName, $sequenceValue );
        } finally {
            self::SemaphoreSignal($handle);
        }


    }

    public static function getNext($sequenceName, $startingNumber = 10000) {
        $db = Db::get();

        $handle = self::SemaphoreWait();

        $number = self::getCurrent($sequenceName, $startingNumber);
        $number += 1;

        $db->query("insert into " . self::TABLE_NAME . " (name, number) values (?,?) on duplicate key update number = ?", [$sequenceName, $number, $number]);

        self::SemaphoreSignal($handle);

        $logger = \Pimcore::getDiContainer()->get("CustomerManagementFramework\\Logger");

        $logger->info("Generated Sequence Number " . $sequenceName . " " . $number . " (pid : " . getmypid() . ")");


        return $number;
    }

    protected static function getLockFilename() {
        $lockFilename = PIMCORE_SYSTEM_TEMP_DIRECTORY . "/cmf-sequence-number.pid";
        return $lockFilename;
    }

    private static function SemaphoreWait() {
        $filename = self::getLockFilename();

        $handle = fopen($filename, 'w') or die("Error opening file.");
        if (flock($handle, LOCK_EX)) {
            //nothing...
        } else {
            die("Could not lock file.");
        }
        return $handle;
    }

    private static function SemaphoreSignal($handle) {
        fclose($handle);
    }
}
