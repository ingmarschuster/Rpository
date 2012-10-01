package com.github.rpository;

import java.io.File;
import java.io.FileOutputStream;
import java.io.IOException;
import java.io.OutputStream;
import java.sql.ResultSet;
import java.sql.SQLException;

import org.apache.commons.compress.archivers.ArchiveOutputStream;
import org.apache.commons.compress.archivers.ArchiveStreamFactory;
import org.apache.commons.compress.archivers.tar.TarArchiveOutputStream;
import org.apache.commons.compress.compressors.gzip.GzipCompressorOutputStream;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.jdbc.core.RowCallbackHandler;
import org.springframework.jdbc.datasource.DriverManagerDataSource;

public class OJSPackager {

	private final String filesStmt = "SELECT F.file_name, F.original_file_name, F.type FROM article_files F WHERE F.article_id = ? ORDER BY file_name";

	private final JdbcTemplate db;
	private final String repoPath;
	private final String filesPath;

	public OJSPackager(JdbcTemplate db, String repoPath, String filesPath) {
		this.db = db;
		this.repoPath = repoPath;
		this.filesPath = filesPath;
	}

	public boolean removeRpackage(int article_id) throws IOException, SQLException {
		final Object[] args = { new Integer(article_id) };
		StringBuilder pkgName = new StringBuilder(repoPath);

		pkgName.append("/");
		db.query(ArticleCallbackHandler.articleStmt, args, new ArticleCallbackHandler(null, pkgName));
		db.query(AuthorsCallbackHandler.authorsStmt, args, new AuthorsCallbackHandler(null, pkgName, null));
		pkgName.append("_1.0.tar.gz");
		return (new File(pkgName.toString())).delete();
	}

	public boolean writeRpackage(int article_id) throws IOException, SQLException {
		/*
		 * set("Package", pkg); set("Version", version); set("License", license); set("Description", descr);
		 * set("Title", title); set("Author", author); set("Maintainer", maintainer);
		 * 
		 * 
		 * Date
		 */
		final String suppPath = filesPath + "/" + article_id + "/supp/";
		final String preprPath = filesPath + "/" + article_id + "/public/";
		final Object[] args = { new Integer(article_id) };
		final PackageDescription pd = new PackageDescription();
		final StringBuilder authors = new StringBuilder("c(");
		final StringBuilder pkgName = new StringBuilder();
		final OutputStream fos;
		final ArchiveOutputStream os;
		final PackageWriter pw;

		pd.set("Repository", "PMR2");
		pd.set("Depends", "R (>= 2.14)");

		db.query(ArticleCallbackHandler.articleStmt, args, new ArticleCallbackHandler(pd, pkgName));

		pd.set("Author", "");
		db.query(AuthorsCallbackHandler.authorsStmt, args, new AuthorsCallbackHandler(authors, pkgName, pd));
		pd.set("Authors@R", authors.append(")").toString());
		pd.set("Package", pkgName.toString());

		fos = new FileOutputStream(repoPath + "/" + pkgName + "_1.0.tar.gz");
		os = new TarArchiveOutputStream(new GzipCompressorOutputStream(fos));
		pkgName.append("/");
		// TODO
		pd.set("Version", "1.0");
		pd.set("License", "GPL (>=3)");

		pw = new PackageWriter(os, new ArchiveEntryFactory(ArchiveStreamFactory.TAR), pd, pkgName.toString());

		db.query(filesStmt, args, new RowCallbackHandler() {
			String submissionPreprintName = null;

			public void processRow(ResultSet rs) throws SQLException {
				final String name = rs.getString(1);
				final String origName = rs.getString(2);
				final String type = rs.getString(3);
				try {
					if (type.equals("supp")) {
						pw.appendLegacy(new File(suppPath + name), origName);
					} else if (type.equals("submission/original")) {
						submissionPreprintName = origName;
					} else if (type.equals("public")) {
						if (submissionPreprintName == null) {
							throw new SQLException(
									"Preprint submission Database entry not processed before publication entry was processed.");
						} else {
							pw.appendPreprint(new File(preprPath + name), submissionPreprintName);
						}
					}
				} catch (IOException ioe) {
					throw new SQLException(ioe);
				}

			}
		});
		pw.close();
		return true;
	}

	public static void main(String[] argv) {
		if (argv.length < 4) {
			System.err.println("Usage: PROGRAM [create|remove] R-Repository-Path Suppl-Files-Path [article_ids]");
			return;
		}
		// 10.0.2.15
		final DriverManagerDataSource ds = new DriverManagerDataSource("jdbc:mysql://10.0.2.15:3306/ojs", "ojs",
				"admin");
		final JdbcTemplate db = new JdbcTemplate(ds);
		final OJSPackager p = new OJSPackager(db, argv[1], argv[2]);
		for (int i = 3; i < argv.length; ++i) {
			try {
				if (argv[0].equals("create")) {
					p.writeRpackage(Integer.parseInt(argv[i]));
				} else if (argv[0].equals("remove")) {
					p.removeRpackage(Integer.parseInt(argv[i]));
				}
			} catch (NumberFormatException e) {
				// TODO Auto-generated catch block
				e.printStackTrace();
			} catch (IOException e) {
				// TODO Auto-generated catch block
				e.printStackTrace();
			} catch (SQLException e) {
				// TODO Auto-generated catch block
				e.printStackTrace();
			}
		}
	}
}
