export async function uploadViaPreparedUrl({
  file,
  prepare,
  confirm,
  localUpload,
  mimeType,
}) {
  const type = mimeType || file.type;
  let prepared = null;
  try {
    prepared = await prepare({
      mimeType: type,
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
      headers: { 'Content-Type': type },
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
