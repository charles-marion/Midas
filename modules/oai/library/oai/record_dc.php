<?php
/*=========================================================================
 MIDAS Server
 Copyright (c) Kitware SAS. 26 rue Louis Guérin. 69100 Villeurbanne, FRANCE
 All rights reserved.
 More information http://www.kitware.com

 Licensed under the Apache License, Version 2.0 (the "License");
 you may not use this file except in compliance with the License.
 You may obtain a copy of the License at

         http://www.apache.org/licenses/LICENSE-2.0.txt

 Unless required by applicable law or agreed to in writing, software
 distributed under the License is distributed on an "AS IS" BASIS,
 WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 See the License for the specific language governing permissions and
 limitations under the License.
=========================================================================*/

// please change to the according metadata prefix you use
$prefix = 'oai_dc';

$output .= '   <metadata>'."\n";
$output .= metadataHeader($prefix);

foreach ($metadata as $m) {
    xmlgetinfo($m->getMetadataId(), $m->getValue());
}

// Here, no changes need to be done
$output .= '     </'.$prefix;
if (isset($METADATAFORMATS[$prefix]['record_prefix'])) {
    $output .= ':'.$METADATAFORMATS[$prefix]['record_prefix'];
}
$output .= ">\n";
$output .= '   </metadata>'."\n";
