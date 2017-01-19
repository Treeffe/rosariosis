<?php
/**
 * Discipline module Portal Alerts
 *
 * @package RosarioSIS
 * @subpackage modules/Discipline
 */

/**
 * Discipline module Portal Alerts
 * Discipline new referrals note.
 *
 * @since 2.9
 *
 * @uses misc/Portal.php|portal_alerts hook
 *
 * @return true if new referrals note, else false.
 */
function DisciplinePortalAlerts()
{
	global $note;

	if ( User( 'PROFILE' ) !== 'admin'
		|| ! AllowUse( 'Discipline/Referrals.php' )
		|| ! $_SESSION['LAST_LOGIN'] )
	{
		return false;
	}

	$last_login_date = mb_substr( $_SESSION['LAST_LOGIN'], 0, 10 );

	$extra = array();

	$extra['SELECT_ONLY'] = 'count(*) AS COUNT';

	$extra['FROM'] = ',DISCIPLINE_REFERRALS dr ';

	$extra['WHERE'] = " AND dr.STUDENT_ID=ssm.STUDENT_ID AND dr.SYEAR=ssm.SYEAR
		AND dr.SCHOOL_ID=ssm.SCHOOL_ID AND
		dr.ENTRY_DATE BETWEEN '" . $last_login_date . "' AND '" . DBDate() . "'";

	$disc_RET = GetStuList( $extra );

	if ( isset( $disc_RET[1]['COUNT'] )
		&& $disc_RET[1]['COUNT'] > 0 )
	{
		$message = '<a href="Modules.php?modname=Discipline/Referrals.php&search_modfunc=list&discipline_entry_begin=' .
			$last_login_date. '&discipline_entry_end=' . DBDate() . '">
			<img src="modules/Discipline/icon.png" class="button bigger" /> ';

		$message .= sprintf(
			ngettext( '%d new referral', '%d new referrals', $disc_RET[1]['COUNT'] ),
			$disc_RET[1]['COUNT']
		);

		$message .= '</a>';

		$note[] = $message;

		return true;
	}

	return false;
}

add_action( 'misc/Portal.php|portal_alerts', 'DisciplinePortalAlerts', 0 );
