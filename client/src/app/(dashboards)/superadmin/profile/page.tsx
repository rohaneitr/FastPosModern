import React from 'react';
import ProfileSettings from '@/features/iam/components/ProfileSettings';

export default function SuperAdminProfilePage() {
  return (
    <div className="h-full">
      <ProfileSettings role="SuperAdmin" />
    </div>
  );
}
