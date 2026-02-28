import { apiRequest } from './apiBase';

export async function submitFormRun(formId, formData, runTitle) {
  return apiRequest('fms_execution_handler.php', 'submit', {
    body: { form_id: formId, form_data: formData, run_title: runTitle },
  });
}

export async function getRun(runId) {
  return apiRequest('fms_execution_handler.php', 'get_run', { params: { id: runId } });
}

export async function listRuns(filters = {}) {
  return apiRequest('fms_execution_handler.php', 'list_runs', { params: filters });
}

export async function startTask(stepId) {
  return apiRequest('fms_execution_handler.php', 'start_step', { body: { step_id: stepId } });
}

export async function completeTask(stepId, payload = {}) {
  return apiRequest('fms_execution_handler.php', 'complete_step', {
    body: { step_id: stepId, ...payload },
  });
}

export async function cancelRun(runId) {
  return apiRequest('fms_execution_handler.php', 'cancel_run', { body: { run_id: runId } });
}

export async function getMyActiveTasks() {
  return apiRequest('fms_execution_handler.php', 'my_tasks');
}

export async function getMyTaskHistory() {
  return apiRequest('fms_execution_handler.php', 'my_history');
}

