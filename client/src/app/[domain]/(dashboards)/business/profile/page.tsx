import React from 'react';
import ProfileSettings from '@/features/iam/components/ProfileSettings';

export default function BusinessProfilePage() {
  return (
    <div className="h-full">
      <ProfileSettings role="BusinessAdmin" />
    </div>
  );
}
