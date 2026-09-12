import { auth } from '../../api/endpoints.js';
import { callApiDelete, callApiPost } from '../../api/apiClient.js';

function unwrapData(response) {
  return response?.data ?? response;
}

export async function uploadMySignatureApi(file) {
  const formData = new FormData();
  formData.append('signature', file);
  const response = await callApiPost(auth.meSignature, formData);
  return unwrapData(response);
}

export async function deleteMySignatureApi() {
  const response = await callApiDelete(auth.meSignature);
  return unwrapData(response);
}
