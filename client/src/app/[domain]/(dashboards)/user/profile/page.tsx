import React from 'react';
import ProfileSettings from '@/features/iam/components/ProfileSettings';

export default function UserProfilePage() {
  return (
    <div className="h-full">
      <ProfileSettings role="User" />
    </div>
  );
}
