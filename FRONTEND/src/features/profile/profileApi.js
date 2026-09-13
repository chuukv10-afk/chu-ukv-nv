import { auth } from '../../api/endpoints.js';
import { callApiDelete, callApiPost } from '../../api/apiClient.js';
import { uploadViaPreparedUrl } from '../../utils/storageUpload.js';

function unwrapData(response) {
  return response?.data ?? response;
}

export async function uploadMySignatureApi(file) {
  return uploadViaPreparedUrl({
    file,
    prepare: async (body) => unwrapData(await callApiPost(`${auth.meSignature}/prepare`, body)),
    confirm: async (body) => unwrapData(await callApiPost(`${auth.meSignature}/confirm`, body)),
    localUpload: async (localFile) => {
      const formData = new FormData();
      formData.append('signature', localFile);
      return unwrapData(await callApiPost(auth.meSignature, formData));
    },
  });
}

export async function deleteMySignatureApi() {
  const response = await callApiDelete(auth.meSignature);
  return unwrapData(response);
}
