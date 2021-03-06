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

/** These are the implementations to generate the web api documents */
class ApidocsComponent extends AppComponent
{
    /**
     * This function is for getting the web API methods information defined in
     * all the API components of the implementing class.
     *
     * @return array
     */
    public function getEnabledResources()
    {
        $apiResources = array();

        $directory = new DirectoryIterator(BASE_PATH.'/core/controllers/components');
        $matches = new RegexIterator($directory, '#Api(.*)Component\.php$#', RegexIterator::GET_MATCH);

        foreach ($matches as $match) {
            if (!in_array($match[1], array('helper', 'docs'))) {
                $apiResources[] = '/'.$match[1];
            }
        }

        $modulesHaveApi = Zend_Registry::get('modulesHaveApi');
        $enabledModules = Zend_Registry::get('modulesEnable');
        $apiModules = array_intersect($modulesHaveApi, $enabledModules);

        foreach ($apiModules as $apiModule) {
            $directory = new DirectoryIterator(BASE_PATH.'/modules/'.$apiModule.'/controllers/components');
            $matches = new RegexIterator($directory, '#Api(.*)Component\.php$#', RegexIterator::GET_MATCH);

            foreach ($matches as $match) {
                if (!in_array($match[1], array(''))) {
                    $apiResources[] = $apiModule.'/'.$match[1];
                }
            }
        }

        return $apiResources;
    }

    /**
     * This function is for getting the web API methods defined in the API
     * component (in given module) of the implementing class.
     *
     * @param string $resource
     * @param string $module
     * @return array
     */
    public function getWebApiDocs($resource, $module = '')
    {
        $apiInfo = array();
        $apiComponent = MidasLoader::loadComponent('Api'.$resource, $module);
        $r = new ReflectionClass($apiComponent);
        $meths = $r->getMethods(ReflectionMethod::IS_PUBLIC);
        foreach ($meths as $m) {
            $name = $m->getName();
            $docString = $m->getDocComment();
            $docString = trim($docString, '/');
            $docAttributes = explode('@', $docString);
            $return = '';
            $description = '';
            $http = '';
            $path = '';
            $resource = '';
            $idParam = 'id';
            $params = array();
            foreach ($docAttributes as $docEntry) {
                $explodedDoc = explode('*', $docEntry);
                array_walk($explodedDoc, create_function('&$val', '$val = trim($val);'));
                $doc = implode('', $explodedDoc);
                if (strpos($doc, 'param') === 0) {
                    $splitParam = explode(' ', $doc);
                    $paramName = trim($splitParam[1]);
                    $paramValue = trim(implode(' ', array_slice($splitParam, 2)));

                    if (strpos($paramName, '$') === 0 || strpos($paramValue, '$') === 0) {
                        continue;
                    }

                    $params[$paramName] = $paramValue;
                } elseif (strpos($doc, 'return') === 0) {
                    $return = trim(substr($doc, 6));
                } elseif (strpos($doc, 'path') === 0) {
                    $path = trim(substr($doc, 5));
                } elseif (strpos($doc, 'http') === 0) {
                    $http = trim(substr($doc, 5));
                } elseif (strpos($doc, 'idparam') === 0) {
                    $idParam = trim(substr($doc, 8));
                } elseif (strpos($doc, 'throws') === 0) {
                   continue;
                } else {
                    $description = $doc;
                }
            }
            if (!empty($path)) {
                $tokens = preg_split('@/@', $path, null, PREG_SPLIT_NO_EMPTY);
                if (empty($module) & !empty($tokens)) { // core
                    $resource = $module.'/'.$tokens[0];
                } elseif (!empty($module) & count($tokens) > 1
                ) { // other modules
                    $resource = $module.'/'.$tokens[1];
                }
            }
            if (empty($resource)) {
                continue;
            }
            $docs = array();
            if ($idParam !== 'id') {
                $params['id'] = $params[$idParam];
                unset($params[$idParam]);
            }
            $docs['params'] = $params;
            $docs['return'] = $return;
            $docs['http'] = $http;
            $docs['path'] = $path;
            $docs['description'] = $description;
            $apiInfo[$resource][$name] = $docs;
        }

        return $apiInfo;
    }

    /**
     * This function is for getting the Swagger Api docs for a single model
     *
     * @param string $resource
     * @param string $module
     * @return array
     */
    public function getResourceApiDocs($resource, $module = '')
    {
        $apiInfo = $this->getWebApiDocs($resource, $module);
        $swaggerDoc = array();
        $swaggerDoc['apiVersion'] = '1.0';
        $swaggerDoc['swaggerVersion'] = '1.1';
        $webroot = Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl();
        $swaggerDoc['basePath'] = $webroot.'/rest/';
        $useSessionParam = array(
            'name' => 'useSession',
            'paramType' => 'query',
            'required' => false,
            'description' => 'Authenticate using the current session',
            'allowMultiple' => false,
            'dataType' => 'string',
        );
        $tokenParam = array(
            'name' => 'token',
            'paramType' => 'query',
            'required' => false,
            'description' => 'Authentication token',
            'allowMultiple' => false,
            'dataType' => 'string',
        );
        if (empty($module)) {
            $swaggerDoc['resourcePath'] = '/'.$resource; // core apis
        } else {
            $swaggerDoc['resourcePath'] = '/'.$module.'/'.$resource; // module apis
        }
        $swaggerDoc['apis'] = array();
        if (array_key_exists($module.'/'.$resource, $apiInfo)) {
            $resourceApiInfo = $apiInfo[$module.'/'.$resource];
            foreach ($resourceApiInfo as $name => $docs) {
                $curApi = array();
                $curApi['path'] = $docs['path'];
                $curApi['operations'] = array();
                $operation = array();
                $operation['httpMethod'] = $docs['http'];
                $operation['summary'] = $docs['description'];
                $operation['notes'] = empty($docs['return']) ? '' : 'Return: '.$docs['return'];
                $operation['nickname'] = $module.'_'.$name;
                $operation['responseClass'] = 'void';
                $operation['parameters'] = array();
                if ($resource !== 'system') {
                    array_push($operation['parameters'], $useSessionParam);
                    array_push($operation['parameters'], $tokenParam);
                }
                foreach ($docs['params'] as $paramName => $paramValue) {
                    $param = array();
                    $param['name'] = $paramName;
                    if ($paramName == 'id') {
                        $param['paramType'] = 'path';
                    } else {
                        $param['paramType'] = 'query';
                    }
                    $param['required'] = true;
                    $prefix = '(Optional)';
                    if (substr($paramValue, 0, strlen($prefix)) == $prefix) {
                        $paramValue = substr($paramValue, strlen($prefix), strlen($paramValue));
                        $param['required'] = false;
                    }
                    $param['description'] = $paramValue;
                    $param['allowMultiple'] = false;
                    $param['dataType'] = 'string';
                    array_push($operation['parameters'], $param);
                }
                array_push($curApi['operations'], $operation);
                array_push($swaggerDoc['apis'], $curApi);
            }
        }

        return $swaggerDoc;
    }
}
