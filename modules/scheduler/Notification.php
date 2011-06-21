<?php
/** notification manager*/
class Scheduler_Notification extends MIDAS_Notification
  {
  public $_moduleModels=array('Job');
  public $_moduleDaos=array('Job');
  public $_components=array('Json');
  public $moduleName = 'scheduler';
  
  /** init notification process*/
  public function init()
    {
    $this->addTask("TASK_SCHEDULER_SCHEDULE_TASK", 'scheduleTask', "Schedule a task. Parameters: task, priority, params");
    }//end init
    
  /** get Config Tabs */
  public function scheduleTask($params)
    {
    $tasks = Zend_Registry::get('notifier')->tasks;
    if(!isset($params['task']) || !isset($tasks[$params['task']]))
      {
      throw new Zend_Exception('Unable to identify task: '.$params['task']);
      }
    if(!isset($params['priority']))
      {
      $params['priority'] = MIDAS_EVENT_PRIORITY_NORMAL;
      }
    if(!isset($params['run_only_once']))
      {
      $params['run_only_once'] = true;
      }
    if(!isset($params['fire_time']))
      {
      $params['fire_time'] = date('c');
      }
    if(!$params['run_only_once'])
      {
      if(!isset($params['time_interval']))
        {
        throw new Zend_Exception('Please set time interval');
        }
      }
    $job = new Scheduler_JobDao();
    $job->setTask($params['task']);
    $job->setPriority($params['priority']);
    $job->setRunOnlyOnce($params['run_only_once']);
    $job->setFireTime($params['fire_time']);
    if(!$params['run_only_once'])
      {
      $job->setTimeInterval($params['time_interval']);
      }
    $job->setStatus(SCHEDULER_JOB_STATUS_TORUN);
    $job->setParams(JsonComponent::encode($params['params']));
    $this->Scheduler_Job->save($job);
    return;
    }
  } //end class
?>