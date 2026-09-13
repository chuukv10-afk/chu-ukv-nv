export async function uploadViaPreparedUrl({
  file,
  prepare,
  confirm,
  localUpload,
}) {
  let prepared = null;
  try {
    prepared = await prepare({
      mimeType: file.type,
      size: file.size,
    });
  } catch (error) {
    if (error?.status !== 404) {
      throw error;
    }
  }

  if (prepared?.mode === 's3' && prepared.uploadUrl) {
    const put = await fetch(prepared.uploadUrl, {
      method: 'PUT',
      headers: { 'Content-Type': file.type },
      body: file,
    });
    if (!put.ok) {
      throw new Error(
        'Impossible d\'envoyer le fichier vers le cloud. Vérifiez le CORS du compartiment S3.',
      );
    }

    return confirm({ filename: prepared.filename });
  }

  return localUpload(file);
}
