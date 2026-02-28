import { apiRequest, apiRequestWithPayloadFallback } from './apiBase';

export async function listForms(flowId) {
  const params = flowId ? { flow_id: flowId } : undefined;
  const json = await apiRequest('fms_form_handler.php', 'list_forms', { params });
  return json.forms || [];
}

export async function listAssignableUsers() {
  const json = await apiRequest('fms_form_handler.php', 'list_users');
  return json.users || [];
}

export async function getForm(formId) {
  return apiRequest('fms_form_handler.php', 'get_form', { params: { id: formId } });
}

export async function createForm(payload) {
  return apiRequestWithPayloadFallback('fms_form_handler.php', 'create_form', payload);
}

export async function updateForm(payload) {
  return apiRequestWithPayloadFallback('fms_form_handler.php', 'update_form', payload);
}

export async function deleteForm(formId) {
  return apiRequest('fms_form_handler.php', 'delete_form', { params: { id: formId }, body: {} });
}

export async function saveFormFields(formId, fields) {
  return apiRequestWithPayloadFallback('fms_form_handler.php', 'save_fields', { form_id: formId, fields });
}

export async function getFlowSteps(flowId) {
  const json = await apiRequest('fms_form_handler.php', 'get_flow_steps', { params: { flow_id: flowId } });
  return json.steps || [];
}

export async function saveFormStepMap(formId, mappings) {
  return apiRequestWithPayloadFallback('fms_form_handler.php', 'save_step_map', { form_id: formId, mappings });
}

