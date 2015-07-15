<?php
require_once 'Functions.php';

function prepareExcelTmpDir($Dir , $Expire = 86400) {
    $HasDir = false;
    if ( !is_dir($Dir) ) {
        if ( mkdir($Dir) ) {
            $HasDir = true;
        }
    } else {
        $HasDir = true;
    }

    if ( $HasDir ) {
        $currTime = time();
        if ( $handle = opendir( $Dir ) ) {
            while ( false !== ( $entry = readdir($handle) ) ) {
                if ( is_file( "$Dir/$entry" ) ) {
                    $fileTime = filemtime( "$Dir/$entry" );
                    if ( $currTime - $fileTime > 86400 ) {
                        unlink( "$Dir/$entry" );
                    }
                }
            }
            closedir($handle);
        }
    }
    return $HasDir;
}

$ExcelTmpDir = '/tmp/LeasingTmp';

if ( !prepareExcelTmpDir($ExcelTmpDir) ) {
    header("Content-type: text/plain");
    die("Error occurred while accessing Excel temporary directory.");
} else {
    require_once 'external/phpexcel/1.8/PHPExcel.php';

    $Filename = isset($_REQUEST['Filename']) ? trim($_REQUEST['Filename']) : 'Export';
    $Sheets = isset($_REQUEST['Sheets']) ? json_decode($_REQUEST['Sheets']) : array();
    $Styles = isset($_REQUEST['Styles']) ? json_decode($_REQUEST['Styles']) : array();

    if ( is_array($Sheets) && !empty($Sheets) ) {
        $PHPExcel = new PHPExcel();

        if ( is_array($Sheets) && !empty($Sheets) ) {
            foreach ( $Sheets as $SheetIndex => $SheetData ) {
                $SheetTitle = isset($SheetData->title) ? trim($SheetData->title) : $SheetIndex;
                $SheetRows = isset($SheetData->rows) ? $SheetData->rows : array();

                if ( !empty($SheetRows) && is_array($SheetRows) ) {
                    $CurrSheet = new PHPExcel_Worksheet($PHPExcel , "$SheetTitle");

                    // Set cell value
                    foreach ( $SheetRows as $CurrRowNum => $SheetRow ) {
                        foreach ( $SheetRow as $CurrColNum => $CurrValue ) {
                            $CurrSheet->setCellValueByColumnAndRow( $CurrColNum , $CurrRowNum + 1 , $CurrValue );
                        }
                    }

                    // Merge cells
                    $Merges = isset($SheetData->merges) ? $SheetData->merges : array();
                    if ( is_array($Merges) ) {
                        foreach ( $Merges as $Merge ) {
                            $ColBegin = isset($Merge->colBegin) ? intval($Merge->colBegin) : -1;
                            $RowBegin = isset($Merge->rowBegin) ? intval($Merge->rowBegin) : 0;
                            $ColEnd = isset($Merge->colEnd) ? intval($Merge->colEnd) : -1;
                            $RowEnd = isset($Merge->rowEnd) ? intval($Merge->rowEnd) : 0;
                            if ( $ColBegin >= 0 && $RowBegin > 0 && $ColEnd >= $ColBegin && $RowEnd >= $RowBegin ) {
                                $CurrSheet->mergeCellsByColumnAndRow($ColBegin,$RowBegin,$ColEnd,$RowEnd);
                            }
                        }
                    }

                    // Set column width
                    $ColWidths = isset($SheetData->colWidths) ? $SheetData->colWidths : array();
                    if ( is_array($ColWidths) ) {
                        foreach ( $ColWidths as $ColNum => $Width ) {
                            if ( $Width > 0 ) {
                                $CurrSheet->getColumnDimensionByColumn($ColNum)->setWidth($Width);
                            }
                        }
                    }

                    // Freeze pane
                    $FreezePane = isset($SheetData->freezePane) ? $SheetData->freezePane : null;
                    if ( is_object($FreezePane) ) {
                        $FreezePaneCol = isset($FreezePane->col) ? intval($FreezePane->col) : -1;
                        $FreezePaneRow = isset($FreezePane->row) ? intval($FreezePane->row) : -1;
                        if ( $FreezePaneCol >= 0 && $FreezePaneRow > 0 ) {
                            $CurrSheet->freezePaneByColumnAndRow($FreezePaneCol , $FreezePaneRow);
                        }
                    }
                    $PHPExcel->addSheet($CurrSheet,$SheetIndex);
                }
            }
            $PHPExcel->removeSheetByIndex($SheetIndex+1);
        }

        if ( is_array($Styles) && !empty($Styles) ) {
            $Sheets = $PHPExcel->getAllSheets();
            foreach ( $Sheets as $SheetIndex => $Sheet ) {
                $CurrStyle = isset($Styles[$SheetIndex]) ? $Styles[$SheetIndex] : null;
                if ( is_object($CurrStyle) ) {
                    //Cell alignments
                    $RowAlignments = isset($CurrStyle->alignments) ? $CurrStyle->alignments : array();
                    foreach ( $RowAlignments as $CurrRowNum => $RowAlignment ) {
                        foreach ( $RowAlignment as $CurrColNum => $Alignment ) {
                            $AlignmentH = isset($Alignment->h) ? strtoupper($Alignment->h) : '';
                            $AlignmentV = isset($Alignment->v) ? strtoupper($Alignment->v) : '';

                            $AlignArr = array();
                            switch ( $AlignmentH ) {
                                case 'CENTER':
                                    $AlignArr['horizontal'] = PHPExcel_Style_Alignment::HORIZONTAL_CENTER;
                                    break;
                                case 'LEFT':
                                    $AlignArr['horizontal'] = PHPExcel_Style_Alignment::HORIZONTAL_LEFT;
                                    break;
                                case 'RIGHT':
                                    $AlignArr['horizontal'] = PHPExcel_Style_Alignment::HORIZONTAL_RIGHT;
                                    break;
                            }
                            switch ( $AlignmentV ) {
                                case 'BOTTOM':
                                    $AlignArr['vertical'] = PHPExcel_Style_Alignment::VERTICAL_BOTTOM;
                                    break;
                                case 'CENTER':
                                    $AlignArr['vertical'] = PHPExcel_Style_Alignment::VERTICAL_CENTER;
                                    break;
                                case 'TOP':
                                    $AlignArr['vertical'] = PHPExcel_Style_Alignment::VERTICAL_TOP;
                                    break;
                            }
                            if ( isset($Alignment->wrap) && is_bool($Alignment->wrap) ) {
                                $AlignArr['wrap'] = $Alignment->wrap;
                            }
                            if ( !empty($AlignArr) ) {
                                $Sheet->getStyleByColumnAndRow($CurrColNum,$CurrRowNum+1)->getAlignment()->applyFromArray($AlignArr);
                            }
                        }
                    }

                    //Cell style
                    $RowDecorations = isset($CurrStyle->decorations) ? $CurrStyle->decorations : array();
                    foreach ( $RowDecorations as $CurrRowNum => $RowDecoration ) {
                        foreach ( $RowDecoration as $CurrColNum => $Decoration ) {
                            $Url = isset($Decoration->url) ? trim($Decoration->url) : '';
                            $FillColor = isset($Decoration->fill_color) ? trim($Decoration->fill_color) : '';
                            $FontColor = isset($Decoration->font_color) ? trim($Decoration->font_color) : '';

                            $DecorationArr = array();
                            if ( !empty($Url) ) {
                                $DecorationArr['font'] = array(
                                    'underline'    => PHPExcel_Style_Font::UNDERLINE_SINGLE ,
                                    'color'        => array(
                                        'rgb'    => '0000EE'
                                    )
                                );
                            } else {
                                if ( isset($Decoration->bold) && is_bool($Decoration->bold) ) {
                                    $DecorationArr['font']['bold'] = $Decoration->bold;
                                }
                                if ( isset($Decoration->italic) && is_bool($Decoration->italic) ) {
                                    $DecorationArr['font']['italic'] = $Decoration->italic;
                                }
                                if ( isset($Decoration->strike) && is_bool($Decoration->strike) ) {
                                    $DecorationArr['font']['strike'] = $Decoration->strike;
                                }

                                $Underline = isset($Decoration->underline) ? strtoupper($Decoration->underline) : '';
                                switch ( $Underline ) {
                                    case 'DOUBLE':
                                        $DecorationArr['font']['underline'] = PHPExcel_Style_Font::UNDERLINE_DOUBLE;
                                        break;
                                    case 'DOUBLE_ACCOUNTING':
                                    case 'DOUBLEACCOUNTING':
                                        $DecorationArr['font']['underline'] = PHPExcel_Style_Font::UNDERLINE_DOUBLEACCOUNTING;
                                        break;
                                    case 'SINGLE':
                                        $DecorationArr['font']['underline'] = PHPExcel_Style_Font::UNDERLINE_SINGLE;
                                        break;
                                    case 'SINGLE_ACCOUNTING':
                                    case 'SINGLEACCOUNTING':
                                        $DecorationArr['font']['underline'] = PHPExcel_Style_Font::UNDERLINE_SINGLEACCOUNTING;
                                        break;
                                }

                                if ( !empty($FontColor) ) {
                                    $DecorationArr['font']['color'] = array('rgb'=>$FontColor);
                                }
                            }

                            if ( !empty($FillColor) ) {
                                $DecorationArr['fill'] = array(
                                    'type'    => PHPExcel_Style_Fill::FILL_SOLID,
                                    'color'    => array(
                                        'rgb'=>$StyleFontColor
                                    )
                                );
                            }

                            if ( !empty($Url) ) {
                                $Sheet->getCellByColumnAndRow($CurrColNum,$CurrRowNum+1)->getHyperlink()->setUrl($Url);

                            }
                            if ( !empty($DecorationArr) ) {
                                $Sheet->getStyleByColumnAndRow($CurrColNum,$CurrRowNum+1)->applyFromArray($DecorationArr);
                            }
                        }
                    }
                }
            }
        }

        $Writer = PHPExcel_IOFactory::createWriter($PHPExcel, 'Excel5');
        $Writer->save("$ExcelTmpDir/$Filename.xls");

        header("Content-type: application/force-download");
        header("Content-Disposition: attachment; filename=\"$Filename.xls\"");
        header("Content-Length: " . filesize("$ExcelTmpDir/$Filename.xls") );
        @readfile("$ExcelTmpDir/$Filename.xls");
    }
}
exit();
