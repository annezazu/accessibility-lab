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
	autoplay?: boolean;
	controls?: boolean;
	loop?: boolean;
	muted?: boolean;
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
				// Muted, looping, autoplaying video without controls is a
				// background/decorative clip with no audio to caption.
				const isBackground =
					attributes.autoplay &&
					attributes.muted &&
					attributes.loop &&
					attributes.controls === false;
				if ( isBackground ) {
					return true;
				}
				const tracks = Array.isArray( attributes.tracks )
					? attributes.tracks
					: [];
				return tracks.some(
					( track ) =>
						Boolean( track?.src ) &&
						// A <track> without `kind` defaults to subtitles.
						[ 'captions', 'subtitles', undefined ].includes(
							track?.kind
						)
				);
			}

			default:
				return isValid;
		}
	}
);
