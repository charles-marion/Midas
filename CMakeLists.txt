#=============================================================================
# MIDAS Server
# Copyright (c) Kitware SAS. 26 rue Louis Guérin. 69100 Villeurbanne, FRANCE
# All rights reserved.
# More information http://www.kitware.com
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
#         http://www.apache.org/licenses/LICENSE-2.0.txt
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.
#=============================================================================

cmake_minimum_required(VERSION 2.8.7)
project(Midas)

get_filename_component(REAL_SOURCE_DIR ${CMAKE_SOURCE_DIR} REALPATH)
get_filename_component(REAL_BINARY_DIR "${CMAKE_BINARY_DIR}" REALPATH)
if(${REAL_SOURCE_DIR} STREQUAL ${REAL_BINARY_DIR})
    message(FATAL_ERROR "Binary directory must not be same as the source directory")
endif()

find_program(PHP "php" DOC "Path to the PHP executable")
if(NOT PHP)
    message(FATAL_ERROR "Please set the path to the PHP executable")
endif()

#-----------------------------------------------------------------------------
# Download and run composer to install third-party dependencies, if necessary
#-----------------------------------------------------------------------------

find_file(COMPOSER_LOCK "composer.lock" PATHS ${CMAKE_CURRENT_SOURCE_DIR} NO_DEFAULT_PATH DOC "Path to the composer lock file")
if(NOT COMPOSER_LOCK)
    find_file(COMPOSER_PHAR "composer.phar" PATHS ${CMAKE_CURRENT_SOURCE_DIR} DOC "Path to the composer PHP archive")
    if(NOT COMPOSER_PHAR)
        message(STATUS "Downloading composer")
        file(DOWNLOAD https://getcomposer.org/composer.phar ${CMAKE_CURRENT_SOURCE_DIR}/composer.phar STATUS DOWNLOAD_COMPOSER_PHAR_STATUS)
        list(GET DOWNLOAD_COMPOSER_PHAR_STATUS 0 DOWNLOAD_COMPOSER_PHAR_RESULT)
        list(GET DOWNLOAD_COMPOSER_PHAR_STATUS 1 DOWNLOAD_COMPOSER_PHAR_ERROR)
        if(NOT DOWNLOAD_COMPOSER_PHAR_RESULT EQUAL 0)
    	    message(FATAL_ERROR "Error downloading composer: ${DOWNLOAD_COMPOSER_PHAR_ERROR}")
        endif()
        message(STATUS "Downloading composer - done")
        set(COMPOSER_PHAR ${CMAKE_CURRENT_SOURCE_DIR}/composer.phar CACHE FILEPATH "Path to the composer PHP archive")
    endif()
    message(STATUS "Executing composer install")
    execute_process(
        COMMAND ${PHP} ${COMPOSER_PHAR} install
        WORKING_DIRECTORY ${CMAKE_CURRENT_SOURCE_DIR}
        RESULT_VARIABLE COMPOSER_INSTALL_RESULT
        OUTPUT_VARIABLE COMPOSER_INSTALL_OUTPUT
        ERROR_VARIABLE COMPOSER_INSTALL_ERROR)
    if(NOT COMPOSER_INSTALL_RESULT EQUAL 0)
        message(STATUS "Composer install output: ${COMPOSER_INSTALL_OUTPUT}")
        message(FATAL_ERROR "Composer install error: ${COMPOSER_INSTALL_ERROR}")
    endif()
    message(STATUS "Executing composer install - done")
endif()

#-----------------------------------------------------------------------------
# Create data, local configs, logs, and temporary directories for testing
#-----------------------------------------------------------------------------
set(ENV{midas_data_path} ${CMAKE_CURRENT_BINARY_DIR}/data)
file(MAKE_DIRECTORY $ENV{midas_data_path})

set(ENV{midas_local_configs_path} ${CMAKE_CURRENT_BINARY_DIR}/configs)
file(MAKE_DIRECTORY $ENV{midas_local_configs_path})

set(ENV{midas_logs_path} ${CMAKE_CURRENT_BINARY_DIR}/logs)
file(MAKE_DIRECTORY $ENV{midas_logs_path})

set(ENV{midas_temp_path} ${CMAKE_CURRENT_BINARY_DIR}/tmp)
file(MAKE_DIRECTORY $ENV{midas_temp_path})

#-----------------------------------------------------------------------------
# Drop all tables from the testing database, then reinstall the database, set
# a default asset store, and install all modules
#-----------------------------------------------------------------------------

message(STATUS "Setting up database(s) for testing")
execute_process(
    COMMAND ${PHP} ${CMAKE_CURRENT_SOURCE_DIR}/tests/DatabaseSetup.php
    RESULT_VARIABLE DATABASE_SETUP_RESULT
    OUTPUT_VARIABLE DATABASE_SETUP_OUTPUT
    ERROR_VARIABLE DATABASE_SETUP_ERROR)
if(NOT DATABASE_SETUP_RESULT EQUAL 0)
    message(STATUS "Database setup output: ${DATABASE_SETUP_OUTPUT}")
    message(FATAL_ERROR "Database setup error: ${DATABASE_SETUP_ERROR}")
endif()
message(STATUS "Setting up database(s) for testing - done")

#-----------------------------------------------------------------------------
# Setup testing and required parameters for testing
#-----------------------------------------------------------------------------

include(CTest)
option(MIDAS_RUN_SECURITY_CHECKS  "Run security checks?" ON)
option(MIDAS_RUN_STYLE_TESTS "Run PHP style tests?" ON)
option(MIDAS_RUN_TESTS_WITH_COVERAGE "Run tests with coverage?" OFF)

add_subdirectory(tests)
add_subdirectory(core/tests)
add_subdirectory(modules)
