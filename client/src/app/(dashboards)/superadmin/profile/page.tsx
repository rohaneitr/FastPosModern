import React from 'react';
import ProfileSettings from '@/components/profile/ProfileSettings';

export default function SuperAdminProfilePage() {
  return (
    <div className="h-full">
      <ProfileSettings role="SuperAdmin" />
    </div>
  );
}
