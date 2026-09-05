import { useEffect, useState } from 'react';
import { Avatar } from '@mui/joy';
import { fetchAuthenticatedAvatarUrl } from '../../utils/avatar.js';

export default function AuthAvatar({
  src,
  fallback,
  size = 'sm',
  variant = 'soft',
  sx,
  ...props
}) {
  const [blobUrl, setBlobUrl] = useState(null);

  useEffect(() => {
    let cancelled = false;

    if (!src) {
      setBlobUrl(null);
      return undefined;
    }

    fetchAuthenticatedAvatarUrl(src)
      .then((url) => {
        if (!cancelled) {
          setBlobUrl(url);
        }
      })
      .catch(() => {
        if (!cancelled) {
          setBlobUrl(null);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [src]);

  return (
    <Avatar
      src={blobUrl ?? undefined}
      size={size}
      variant={variant}
      sx={sx}
      {...props}
    >
      {!blobUrl ? fallback : null}
    </Avatar>
  );
}
