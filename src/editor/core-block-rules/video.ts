/**
 * core/video validation logic.
 */

import { addFilter } from '@wordpress/hooks';

type Track = {
	src?: string;
	kind?: string;
	label?: string;
	srcLang?: string;
};

type VideoAttributes = {
	id?: number;
	src?: string;
	tracks?: Track[];
};

addFilter(
	'editor.validateBlock',
	'accessibility-lab-core-blocks/video',
	(
		isValid: boolean,
		blockType: string,
		attributes: VideoAttributes,
		checkName: string
	) => {
		if ( blockType !== 'core/video' ) {
			return isValid;
		}

		const hasVideo = Boolean( attributes.src || attributes.id );

		switch ( checkName ) {
			case 'check_video_tracks': {
				// If no video file is attached yet, do not trigger unconfigured warning.
				if ( ! hasVideo ) {
					return true;
				}
				const tracks = Array.isArray( attributes.tracks )
					? attributes.tracks
					: [];
				return tracks.some(
					( track ) =>
						Boolean( track?.src ) &&
						( track?.kind === 'captions' ||
							track?.kind === 'subtitles' )
				);
			}

			default:
				return isValid;
		}
	}
);
