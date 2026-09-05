import Box from '@mui/joy/Box';
import Typography from '@mui/joy/Typography';
import { getRoleAssignmentParts } from '../../utils/profile.js';

export default function RoleAssignmentLabel({ assignment, preferCode = false, component = Typography, ...props }) {
  const { roleLabel, scope } = getRoleAssignmentParts(assignment, { preferCode });
  const Wrapper = component;

  return (
    <Wrapper {...props}>
      <Box component="span" sx={{ fontWeight: 700 }}>{roleLabel}</Box>
      {` : ${scope}`}
    </Wrapper>
  );
}
