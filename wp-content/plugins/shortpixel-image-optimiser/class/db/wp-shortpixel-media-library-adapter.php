<?php
use ShortPixel\ShortpixelLogger\ShortPixelLogger as Log;

class WpShortPixelMediaLbraryAdapter {

    //count all the processable files in media library (while limiting the results to max 10000)
    public static function countAllProcessableFiles($settings = array(), $maxId = PHP_INT_MAX, $minId = 0){
        global  $wpdb;

        $totalFiles = $mainFiles = $processedMainFiles = $processedTotalFiles = $totalFilesM1 = $totalFilesM2 = $totalFilesM3 = $totalFilesM4 =
        $procGlossyMainFiles = $procGlossyTotalFiles = $procLossyMainFiles = $procLossyTotalFiles = $procLosslessMainFiles = $procLosslessTotalFiles = $procUndefMainFiles = $procUndefTotalFiles = $mainUnprocessedThumbs = 0;
        $filesMap = $processedFilesMap = array();
        $limit = self::getOptimalChunkSize();
        $pointer = 0;
        $filesWithErrors = array(); $moreFilesWithErrors = 0;
        $excludePatterns = WPShortPixelSettings::getOpt("excludePatterns");

        if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
            $month1 = new DateTime();
            $month2 = new DateTime();
            $month3 = new DateTime();
            $month4 = new DateTime();
            $mi1 = new DateInterval('P1M');
            $mi2 = new DateInterval('P2M');
            $mi3 = new DateInterval('P3M');
            $mi4 = new DateInterval('P4M');
            $month1->sub($mi1);
            $month2->sub($mi2);
            $month3->sub($mi3);
            $month4->sub($mi4);
        }

        $counter = 0; $foundUnlistedThumbs = false;

        //count all the files, main and thumbs
        while ( 1 ) {
            $idInfo = self::getPostIdsChunk($minId, $maxId, $pointer, $limit);
            if($idInfo === null) {
                break; //we parsed all the results
            }
            elseif(count($idInfo->ids) == 0) {
                $pointer += $limit;
                continue;
            }

            $filesList= $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "postmeta
                                        WHERE post_id IN (" . implode(',', $idInfo->ids) . ")
                                          AND ( meta_key = '_wp_attached_file' OR meta_key = '_wp_attachment_metadata' )");

            //in one case this query returned zero items but if fewer items in the IDs list, it worked so apply this workaround:
            if($limit > 1000 && count($filesList) == 0) {
                $limit = 1000;
                continue;
            }

            foreach ( $filesList as $file )
            {
                $totalFilesThis = $processedFilesThis = 0;

                if ( $file->meta_key == "_wp_attached_file" )
                {//count pdf files only
                    $extension = substr($file->meta_value, strrpos($file->meta_value,".") + 1 );
                    if ( $extension == "pdf" && $settings->optimizePdfs && !isset($filesMap[$file->meta_value]))
                    {
                        $totalFiles++;
                        $totalFilesThis++;
                        $mainFiles++;
                        $filesMap[$file->meta_value] = 1;
                    }
                }
                elseif ( $file->meta_key == "_wp_attachment_metadata" ) //_wp_attachment_metadata
                {
                    $attachment = @unserialize($file->meta_value);
                    $sizesCount = isset($attachment['sizes']) ? self::countSizesNotExcluded($attachment['sizes'], $settings->excludeSizes) : 0;

                    // LA FIECARE 100 de imagini facem un test si daca findThumbs da diferit, sa dam o avertizare si eventual optiune
                    $dismissed = $settings->dismissedNotices ? $settings->dismissedNotices : array();
                    if( $foundUnlistedThumbs === false && $maxId == PHP_INT_MAX && !isset($dismissed['unlisted'])
                        && (   in_array($counter, array(2,4,6,8)) || floor($counter/100) == 0 && $counter%10 == 0
                            || floor($counter/1000) == 0 && $counter%100 == 0 || floor($counter/10000) == 0 && $counter%1000 == 0))
                    {
                        $filePath = isset($attachment['file']) ? trailingslashit(SHORTPIXEL_UPLOADS_BASE).$attachment['file'] : false;
                        if ($filePath && file_exists($filePath) && isset($attachment['sizes']) &&
                            (   !isset($attachment['ShortPixelImprovement']) || $attachment['ShortPixelImprovement'] === 0
                             || $attachment['ShortPixelImprovement'] === 0.0 || $attachment['ShortPixelImprovement'] === "0"))
                        {
                            $foundThumbs = WpShortPixelMediaLbraryAdapter::findThumbs($filePath);

                            $foundCount = count($foundThumbs);

                            if(count($foundThumbs) > $sizesCount) {
                                $unlisted = array();
                                foreach($foundThumbs as $found) {
                                    $match = ShortPixelTools::findItem(wp_basename($found), $attachment['sizes'], 'file');
                                    if(!$match) {
                                        $unlisted[] = wp_basename($found);
                                    }
                                }
                                $foundUnlistedThumbs = (object)array("id" => $file->post_id, "name" => wp_basename($attachment['file']), "unlisted" => $unlisted);
                            }
                        } else {
                            $counter--; // will take the next one
                            $realSizesCount = $sizesCount;
                        }
                    }
                    $counter++;

                    //processable
                    $isProcessable = false;
                    $isProcessed = isset($attachment['ShortPixelImprovement'])
                        && ($attachment['ShortPixelImprovement'] > 0 || $attachment['ShortPixelImprovement'] === 0.0 || $attachment['ShortPixelImprovement'] === "0")
                        //for PDFs there is no file field so just let it pass.
                        && (!isset($attachment['file']) || !isset($processedFilesMap[$attachment['file']]));

                    if(   isset($attachment['file']) && !isset($filesMap[$attachment['file']])
                       && WPShortPixel::_isProcessablePath($attachment['file'], array(), $excludePatterns)){
                        $isProcessable = true;
                        $totalFiles += $sizesCount;
                        $totalFilesThis += $sizesCount;
                        if ( isset($attachment['file']) )
                        {
                            $totalFiles++;
                            $totalFilesThis++;
                            $mainFiles++;
                            $filesMap[$attachment['file']] = 1;
                        }
                    }
                    //processed
                    if ($isProcessed) {
                        //add main file to counts
                        $processedMainFiles++;
                        $processedTotalFiles++;
                        $processedFilesThis++;
                        $type = isset($attachment['ShortPixel']['type']) ? $attachment['ShortPixel']['type'] : null;
                        switch($type) {
                            case 'lossy' :
                                $procLossyMainFiles++;
                                $procLossyTotalFiles++;
                                break;
                            case 'glossy':
                                $procGlossyMainFiles++;
                                $procGlossyTotalFiles++;
                                break;
                            case 'lossless':
                                $procLosslessMainFiles++;
                                $procLosslessTotalFiles++;
                                break;
                            default:
                                $procUndefMainFiles++;
                                $procUndefTotalFiles++;
                        }

                        //get the thumbs processed for that attachment
                        $thumbs = $allThumbs = 0;
                        if ( isset($attachment['ShortPixel']['thumbsOpt']) ) {
                            $thumbs = $attachment['ShortPixel']['thumbsOpt'];
                        }
                        elseif ( isset($attachment['sizes']) ) {
                            $thumbs = $sizesCount;
                        }
                        if(!isset($attachment['file'])) { //for the pdfs that have thumbs, have to add the thumbs too (not added above )
                            $totalFiles += $thumbs;
                            $totalFilesThis += $thumbs;
                        }
                        $thumbsMissing = isset($attachment['ShortPixel']['thumbsMissing']) ? $attachment['ShortPixel']['thumbsMissing'] : array();

                        if ( isset($attachment['sizes']) && $sizesCount > $thumbs + count($thumbsMissing)) {
                            $mainUnprocessedThumbs++;
                        }

                        //increment with thumbs processed
                        $processedTotalFiles += $thumbs;
                        $processedFilesThis += $thumbs;
                        if($type == 'glossy') {
                           $procGlossyTotalFiles += $thumbs;
                        } elseif ($type == 'lossy') {
                           $procLossyTotalFiles += $thumbs;
                        } else {
                           $procLosslessTotalFiles += $thumbs;
                        }

                        if ( isset($attachment['file']) ) {
                            $processedFilesMap[$attachment['file']] = 1;
                        }
                    }
                    elseif($isProcessable && isset($attachment['ShortPixelImprovement'])) {
                        if(count($filesWithErrors) < 50) {
                            $filePath = explode("/", $attachment["file"]);
                            $name = is_array($filePath)? $filePath[count($filePath) - 1] : $file->post_id;
                            $filesWithErrors[$file->post_id] = array('Id' => $file->post_id, 'Name' => $name, 'Message' => $attachment['ShortPixelImprovement']);
                        } else {
                            $moreFilesWithErrors++;
                        }
                    }

                }

                if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
                    $dt = new DateTime($idInfo->idDates[$file->post_id]);
                    if ($dt > $month1) {
                        $totalFilesM1 += $totalFilesThis;
                    } else if ($dt > $month2) {
                        $totalFilesM2 += $totalFilesThis;
                    } else if ($dt > $month3) {
                        $totalFilesM3 += $totalFilesThis;
                    } else if ($dt > $month4) {
                        $totalFilesM4 += $totalFilesThis;
                    }
                }
            }
            unset($filesList);
            $pointer += $limit;
        }//end while

        return array("totalFiles" => $totalFiles, "mainFiles" => $mainFiles,
                     "totalProcessedFiles" => $processedTotalFiles, "mainProcessedFiles" => $processedMainFiles,
                     "totalProcLossyFiles" => $procLossyTotalFiles, "mainProcLossyFiles" => $procLossyMainFiles,
                     "totalProcGlossyFiles" => $procGlossyTotalFiles, "mainProcGlossyFiles" => $procGlossyMainFiles,
                     "totalProcLosslessFiles" => $procLosslessTotalFiles, "mainProcLosslessFiles" => $procLosslessMainFiles,
                     "totalMlFiles" => $totalFiles, "mainMlFiles" => $mainFiles,
                     "totalProcessedMlFiles" => $processedTotalFiles, "mainProcessedMlFiles" => $processedMainFiles,
                     "totalProcLossyMlFiles" => $procLossyTotalFiles, "mainProcLossyMlFiles" => $procLossyMainFiles,
                     "totalProcGlossyMlFiles" => $procGlossyTotalFiles, "mainProcGlossyMlFiles" => $procGlossyMainFiles,
                     "totalProcLosslessMlFiles" => $procLosslessTotalFiles, "mainProcLosslessMlFiles" => $procLosslessMainFiles,
                     "totalProcUndefMlFiles" => $procUndefTotalFiles, "mainProcUndefMlFiles" => $procUndefMainFiles,
                     "mainUnprocessedThumbs" => $mainUnprocessedThumbs, "totalM1" => $totalFilesM1, "totalM2" => $totalFilesM2, "totalM3" => $totalFilesM3, "totalM4" => $totalFilesM4,
                     "filesWithErrors" => $filesWithErrors,
                     "moreFilesWithErrors" => $moreFilesWithErrors,
                     "foundUnlistedThumbs" => $foundUnlistedThumbs
                    );
    }

    public static function getPostMetaSlice($startId, $endId, $limit) {
        global $wpdb;
        $time = microtime(true);
        $queryPostMeta = "SELECT * FROM " . $wpdb->prefix . "postmeta pm
            INNER JOIN " . $wpdb->prefix . "posts p ON p.ID = pm.post_id
            WHERE ( p.ID <= $startId AND p.ID >= $endId )
              AND ( pm.meta_key = '_wp_attached_file' OR pm.meta_key = '_wp_attachment_metadata' )
            ORDER BY pm.post_id DESC
            LIMIT " . $limit;
        $result =  $wpdb->get_results($queryPostMeta);
        $time_end = microtime(true);
    //    Log::addDebug('Post Meta Slice query took ' . ($time_end-$time) . ' sec. - Result count ' . count($result), array( $queryPostMeta));
        return $result;
    }

  /*  public static function getPostMetaJoinLess($startId, $endId, $limit)
    {
      global $wpdb;
      $time = microtime(true);
      $sql =  "SELECT ID FROM " . $wpdb->prefix . "posts WHERE ID <= %d AND ID >= %d ORDER BY ID DESC LIMIT %d ";
      $sql = $wpdb->prepare($sql, $startId, $endId, $limit);
      $result = $wpdb->get_col($sql);

      if (is_null($result))
        return array();

      $id_placeholders = implode( ', ', array_fill( 0, count( $result ), '%d'));

      $sqlmeta = "SELECT DISTINCT post_id, meta_key, meta_value FROM " . $wpdb->prefix . "postmeta where (meta_key = %s or meta_key = %s) and post_id in (" . $id_placeholders . ") order by post_id DESC";

      $placeholders = array_merge(array('_wp_attached_file', '_wp_attachment_metadata'), array_values($result));
      $sqlmeta = $wpdb->prepare($sqlmeta, $placeholders);
      $metaresult = $wpdb->get_results($sql);

      $time_end = microtime(true);

  //    Log::addDebug('Post Meta JoinLESS query took ' . ($time_end-$time) . ' sec. - Result count ' . count($metaresult), array($sql, $sqlmeta));

      return $metaresult;
    }
*/
    public static function getPostsJoinLessReverse($startId, $endId, $limit)
    {
      global $wpdb;
      //$time = microtime(true);

      $sqlmeta = "SELECT DISTINCT post_id FROM " . $wpdb->prefix . "postmeta where (meta_key = %s or meta_key = %s) and post_id <= %d and post_id >= %d order by post_id DESC LIMIT %d";
      $sqlmeta = $wpdb->prepare($sqlmeta, '_wp_attached_file', '_wp_attachment_metadata', $startId, $endId, $limit);

      $result = $wpdb->get_col($sqlmeta);

      // no postmeta present, i.e. empty installation
      if (count($result) == 0)
        return array();

      $id_placeholders = implode( ', ', array_fill( 0, count( $result ), '%d'));

      $sql = 'SELECT ID from ' . $wpdb->prefix . 'posts where ID in (' . $id_placeholders . ') ORDER BY ID DESC';
      $sql = $wpdb->prepare($sql, array_values($result));

      $postresult = $wpdb->get_col($sql);

      $postAr = array_intersect($result, $postresult);

      //$time_end = microtime(true);
      return $postAr;
    }

    public static function getSizesNotExcluded($sizes, $exclude = false) {
        $uniq = array();
        $exclude = is_array($exclude) ? $exclude : array(); //this is because it sometimes receives directly the setting which could be false
        foreach($sizes as $key => $val) {
            if (strpos($key, ShortPixelMeta::WEBP_THUMB_PREFIX) === 0) continue;
            if (isset($val['mime-type']) && $val['mime-type'] == "image/webp") continue;
            if(!isset($val['file'])) continue;
            if (in_array($key, $exclude)) continue;
            $file = $val['file'];
            if(is_array($file)) { $file = $file[0];} // HelpScout case 709692915
            $uniq[$file] = $key;
        }
        return $uniq;
    }

    public static function countSizesNotExcluded($sizes, $exclude= false)
    {
        return count(self::getSizesNotExcluded($sizes, $exclude));
    }

    /** @todo Seems not to be in use - Only referenced from wp-short-pixel handling */
    /*
    public static function cleanupFoundThumbs($itemHandler) {
        $meta = $itemHandler->getMeta();
        $sizesAll = $meta->getThumbs();
        $sizes = array();
        $files = array();
        foreach($sizesAll as $key => $size) {
            if(strpos($key, ShortPixelMeta::FOUND_THUMB_PREFIX) === 0) continue;
            if(!isset($size['file'])) continue;
            $sizes[$key] = $size;
            $file = $size['file'];
            if(is_array($file)) { $file = $file[0];} // HelpScout case 709692915
            if(in_array($file, $files)) continue;
            $files[] = $file;
        }
        $meta->setThumbs($sizes);
        $itemHandler->updateMeta($meta, true);
    } */

    /** Find thumbnails that are not listed in the image metadata
    * These are usually thumbnails generated by other plugins
    * @param $mainFile String Full path the main Image of which to get thumbnails
    * @return Array Array of thumbnails or empty array
    */
    public static function findThumbs($mainFile) {
        // Old
      /*  $ext = pathinfo($mainFile, PATHINFO_EXTENSION); // gets the extension
        $base = substr($mainFile, 0, strlen($mainFile) - strlen($ext) - 1); // removes the extension
        $pattern = '/' . preg_quote($base, '/') . '-\d+x\d+\.'. $ext .'/'; // tries to match between basename and extension
      */
        $thumbs = array();

        // New
        $fs = new \ShortPixel\FileSystemController();
        $file = $fs->getFile($mainFile);
        $dirPath = $file->getFileDir()->getPath();

        $base = $file->getFileBase();
        $ext = $file->getExtension();
        $pattern = '/' . preg_quote($base, '/') . '-\d+x\d+\.'. $ext .'/';

        $thumbs = array_merge($thumbs, self::getFilesByPattern($dirPath, $pattern));

        /*$dirIterator = new \DirectoryIterator($dirPath);
        $regExIterator = new \RegexIterator($dirIterator, $pattern);

        foreach($regExIterator as $fileinfo)
        {
          $thumbs[] = $fileinfo->getPath();
        } */

/*
        $thumbsCandidates = @glob($base . "-*." . $ext); */
//        $thumbs = array();
      /*  if(is_array($thumbsCandidates)) {
            foreach($thumbsCandidates as $th) {
                if(preg_match($pattern, $th)) {
                    $thumbs[]= $th;
                }
            } */
            if( defined('SHORTPIXEL_CUSTOM_THUMB_SUFFIXES') ){
                $suffixes = explode(',', SHORTPIXEL_CUSTOM_THUMB_SUFFIXES);
                if (is_array($suffixes))
                {
                  foreach ($suffixes as $suffix){

                      $pattern = '/' . preg_quote($base, '/') . '-\d+x\d+'. $suffix . '\.'. $ext .'/';
                      $thumbs = array_merge($thumbs, self::getFilesByPattern($dirPath, $pattern));
                      /*foreach($thumbsCandidates as $th) {
                          if(preg_match($pattern, $th)) {
                              $thumbs[]= $th;
                          }
                      } */
                  }
                }
            }
            if( defined('SHORTPIXEL_CUSTOM_THUMB_INFIXES') ){
                $infixes = explode(',', SHORTPIXEL_CUSTOM_THUMB_INFIXES);
                if (is_array($infixes))
                {
                  foreach ($infixes as $infix){
                      //$thumbsCandidates = @glob($base . $infix  . "-*." . $ext);
                      $pattern = '/' . preg_quote($base, '/') . $infix . '-\d+x\d+' . '\.'. $ext .'/';
                      $thumbs = array_merge($thumbs, self::getFilesByPattern($dirPath, $pattern));

                      /*foreach($thumbsCandidates as $th) {
                          if(preg_match($pattern, $th)) {
                              $thumbs[]= $th;
                          }
                      } */
                  }
                }
            }
      //  }
        return $thumbs;
    }

    private static function getFilesByPattern($path, $pattern)
    {

      $dirIterator = new \DirectoryIterator($path);
      $regExIterator = new \RegexIterator($dirIterator, $pattern);

      $images = array();
      foreach($regExIterator as $fileinfo)
      {
        $images[] = $fileinfo->getPathname();
      }

      return $images;
    }

    public static function getOptimalChunkSize($table = 'posts') {
        global  $wpdb;
        //get an aproximate but fast row count.
        $row = $wpdb->get_results("EXPLAIN SELECT count(*) from " . $wpdb->prefix . $table);
        if(isset($row['rows'])) {
            $cnt = $row['rows'];
        } else {
            $cnt = $wpdb->get_results("SELECT count(*) posts FROM " . $wpdb->prefix . $table);
        }
        //json_encode($wpdb->get_results("SHOW VARIABLES LIKE 'max_allowed_packet'"));
        $posts = isset($cnt) && count($cnt) > 0 ? $cnt[0]->posts : 0;
        if($posts > 100000) {
            return 10000;
        } elseif ($posts > 50000) {
            return 5000;
        } elseif($posts > 10000) {
            return 2000;
        } else {
            return 500;
        }
    }

    protected static function getPostIdsChunk($minId, $maxId, $pointer, $limit) {
        global  $wpdb;

        $ids = $idDates = array();
        $idList = $wpdb->get_results("SELECT ID, post_mime_type, post_date FROM " . $wpdb->prefix . "posts
                                    WHERE ( ID <= $maxId AND ID > $minId )
                                    LIMIT $pointer,$limit");
        if ( empty($idList) ) {
            return null;
        }
        foreach($idList as $item) {
            if($item->post_mime_type != '') {
                $ids[] = $item->ID;
                $idDates[$item->ID] = $item->post_date;
            }
        }
        return (object)array('ids' => $ids, 'idDates' => $idDates);
    }

}
